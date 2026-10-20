<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Services\LocationInventoryAccessService;
use App\Services\SalesOrderService;
use App\Services\OrderSmsService;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $orders,
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly OrderSmsService $sms,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->locationAccess->scopeViewable(
            SalesOrder::query()->with(['location:id,name,code', 'creator:id,name', 'items']),
            $request->user(),
        )->orderByDesc('id');
        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'processing', 'partially_fulfilled', 'fulfilled', 'cancelled'])]])['status']);
        }
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('order_number', 'ilike', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%')
                    ->orWhere('customer_name', 'ilike', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%')
                    ->orWhere('customer_phone', 'ilike', '%' . str_replace(['%', '_'], ['\\%', '\\_'], $term) . '%');
            });
        }
        $orders = $query->paginate(min(max((int) $request->input('per_page', 15), 1), 50));
        return response()->json($orders);
    }

    public function options(Request $request): JsonResponse
    {
        $locations = Location::query()->active()->orderBy('name')->get(['id', 'name', 'code'])
            ->filter(fn (Location $location): bool => $this->locationAccess->canView($request->user(), (int) $location->id))
            ->values();

        $locationId = $request->integer('location_id');
        $variants = collect();
        if ($locationId > 0) {
            $this->locationAccess->assertView($request->user(), $locationId);
            $variants = ProductVariant::query()->with('product:id,name,sku,status')
                ->where('status', 'active')->whereHas('product', fn ($q) => $q->where('status', 'active'))
                ->orderBy('product_id')->orderBy('sort_order')
                ->get(['id', 'product_id', 'name', 'sku', 'selling_price', 'promotional_price', 'promotion_starts_at', 'promotion_ends_at'])
                ->map(function (ProductVariant $variant) use ($locationId): array {
                    $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->where('location_id', $locationId)->first();
                    return [
                        'id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'name' => $variant->product?->name . ($variant->name ? ' · ' . $variant->name : ''),
                        'sku' => $variant->sku ?: $variant->product?->sku,
                        'price' => $variant->effectiveSellingPrice(),
                        'available' => $stock ? bcsub((string) $stock->quantity, (string) $stock->reserved_quantity, 4) : '0.0000',
                    ];
                });
        }
        return response()->json(['locations' => $locations, 'variants' => $variants]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'customer_name' => ['required', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'channel' => ['nullable', Rule::in(['backoffice', 'website', 'mobile_app'])],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        $order = $this->orders->create($data, $request->user());
        return response()->json(['message' => 'Order created and stock reserved.', 'data' => $order], 201);
    }

    public function show(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertView($request->user(), (int) $order->location_id);
        $order->load(['location:id,name,code', 'creator:id,name', 'items.fulfillmentItems', 'statusHistory.actor:id,name', 'fulfillments.items.orderItem', 'shipments', 'riskAssessments', 'smsLogs']);
        $order->items->each(function ($item): void {
            $done = $item->fulfillmentItems->sum(fn ($line) => (float) $line->quantity);
            $item->setAttribute('fulfilled_quantity', number_format($done, 4, '.', ''));
            $item->setAttribute('remaining_quantity', number_format(max(0, (float) $item->quantity - $done), 4, '.', ''));
        });
        return response()->json(['data' => $order]);
    }

    public function confirm(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $assessment = $order->riskAssessments()->latest('id')->first();
        if ($assessment && in_array($assessment->decision, ['manual_review', 'reject'], true)) {
            throw ValidationException::withMessages(['risk' => 'This order requires an approved risk review before confirmation.']);
        }
        if ($assessment && $assessment->decision === 'verify' && !\App\Models\SalesOrderPhoneVerification::query()->where('sales_order_id', $order->id)->whereNotNull('verified_at')->exists()) {
            throw ValidationException::withMessages(['risk' => 'Verify the customer phone before confirming this order.']);
        }
        $updated = $this->orders->confirm($order, $request->user());
        if ($updated->customer_phone) $this->sms->sendOrderEvent($updated, 'confirmed');
        return response()->json(['message' => 'Order confirmed.', 'data' => $updated]);
    }

    public function fulfill(Request $request, SalesOrder $order): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.sales_order_item_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $updated = $this->orders->fulfill($order, $data, $request->user());
        return response()->json(['message' => 'Fulfillment recorded and stock ledger updated.', 'data' => $updated]);
    }

    public function cancel(Request $request, SalesOrder $order): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $updated = $this->orders->cancel($order, $request->user(), trim($data['reason']));
        return response()->json(['message' => 'Order cancelled and active stock reservations released.', 'data' => $updated]);
    }
}
