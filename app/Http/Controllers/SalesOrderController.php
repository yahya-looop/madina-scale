<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderPhoneVerification;
use App\Models\SalesOrderAdjustment;
use App\Models\FinancialTransaction;
use App\Models\SalesOrderAdjustmentSettlement;
use App\Services\LocationInventoryAccessService;
use App\Services\OrderSmsService;
use App\Services\SalesOrderService;
use App\Services\SalesOrderAdjustmentService;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $orders,
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly OrderSmsService $sms,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $baseScope = $this->locationAccess->scopeViewable(
            SalesOrder::query(),
            $request->user(),
        );
        $stats = [
            'total' => (clone $baseScope)->count(),
            'active' => (clone $baseScope)->whereIn('status', ['pending', 'confirmed', 'processing', 'partially_fulfilled'])->count(),
            'pending' => (clone $baseScope)->where('status', 'pending')->count(),
            'fulfilled' => (clone $baseScope)->where('status', 'fulfilled')->count(),
        ];
        $query = $this->locationAccess->scopeViewable(
            SalesOrder::query()->with(['location:id,name,code', 'creator:id,name', 'items']),
            $request->user(),
        )->orderByDesc('id');
        if ($request->filled('status') && $request->string('status')->toString() !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['pending', 'confirmed', 'processing', 'partially_fulfilled', 'fulfilled', 'cancelled'])]])['status']);
        }
        if ($request->filled('channel') && $request->string('channel')->toString() !== 'all') {
            $query->where('channel', $request->validate(['channel' => ['required', Rule::in(['backoffice', 'storefront', 'mobile_app'])]])['channel']);
        }
        if ($request->filled('search')) {
            $term = trim((string) $request->input('search'));
            $query->where(function ($q) use ($term): void {
                $q->where('order_number', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%')
                    ->orWhere('customer_name', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%')
                    ->orWhere('customer_phone', 'ilike', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%');
            });
        }
        $orders = $query->paginate(min(max((int) $request->input('per_page', 15), 1), 50));

        return response()->json(array_merge($orders->toArray(), ['stats' => $stats]));
    }

    public function options(Request $request): JsonResponse
    {
        $locations = Location::query()->active()->orderBy('name')->get(['id', 'name', 'code'])
            ->filter(fn (Location $location): bool => $this->locationAccess->canView($request->user(), (int) $location->id))
            ->values();

        $locationId = $request->integer('location_id');
        $editingOrderId = $request->integer('order_id');
        $editingOrder = null;
        if ($editingOrderId > 0) {
            $editingOrder = SalesOrder::query()->findOrFail($editingOrderId);
            $this->locationAccess->assertManage($request->user(), (int) $editingOrder->location_id);
            if ($editingOrder->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending orders can be edited.']);
            }
            if ($locationId !== (int) $editingOrder->location_id) {
                throw ValidationException::withMessages(['location_id' => 'The order location cannot be changed while editing.']);
            }
        }
        $variants = collect();
        if ($locationId > 0) {
            $this->locationAccess->assertView($request->user(), $locationId);

            // Load only catalog variants whose product has an inventory owner at
            // this location. A simple product may own stock directly via product_id;
            // variable products own stock per variant. Zero-stock rows remain visible.
            $variantRecords = ProductVariant::query()
                ->with(['product:id,name,sku,status,product_type', 'product.primaryMedia'])
                ->where('status', 'active')
                ->whereHas('product', fn ($q) => $q->where('status', 'active'))
                ->orderBy('product_id')->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'product_id', 'name', 'sku', 'selling_price', 'promotional_price', 'promotion_starts_at', 'promotion_ends_at', 'is_default']);

            $stockRows = InventoryStock::query()
                ->where('location_id', $locationId)
                ->where(function ($q) use ($variantRecords): void {
                    $q->whereIn('product_variant_id', $variantRecords->pluck('id'))
                        ->orWhereIn('product_id', $variantRecords->pluck('product_id')->unique());
                })
                ->get(['product_id', 'product_variant_id', 'quantity', 'reserved_quantity']);
            $stockByVariant = $stockRows->filter(fn ($row) => $row->product_variant_id !== null)->keyBy('product_variant_id');
            $stockByProduct = $stockRows->filter(fn ($row) => $row->product_id !== null)->keyBy('product_id');

            $ownReservations = $editingOrder
                ? InventoryReservation::query()
                    ->selectRaw('product_id, product_variant_id, SUM(quantity) AS reserved_for_order')
                    ->where('reference_type', 'sales_order')
                    ->where('reference_id', (string) $editingOrder->id)
                    ->where('status', 'active')
                    ->groupBy('product_id', 'product_variant_id')
                    ->get()
                : collect();

            $variants = $variantRecords
                ->map(function (ProductVariant $variant) use ($stockByVariant, $stockByProduct, $ownReservations): ?array {
                    $variantStock = $stockByVariant->get($variant->id);
                    $productStock = $variant->product->product_type === 'simple'
                        ? $stockByProduct->get($variant->product_id)
                        : null;

                    // Prefer variant-owned inventory; use product-owned inventory
                    // only for a simple product, and show its default variant once.
                    if ($variantStock) {
                        $stock = $variantStock;
                        $ownerType = 'variant';
                        $ownerId = $variant->id;
                    } elseif ($productStock && $variant->is_default) {
                        $stock = $productStock;
                        $ownerType = 'product';
                        $ownerId = $variant->product_id;
                    } else {
                        return null;
                    }

                    $available = bcsub((string) $stock->quantity, (string) $stock->reserved_quantity, 4);
                    $matchingReservation = $ownReservations->first(fn ($reservation) => $ownerType === 'variant'
                        ? (int) $reservation->product_variant_id === (int) $ownerId
                        : (int) $reservation->product_id === (int) $ownerId && $reservation->product_variant_id === null);
                    $ownReserved = (string) ($matchingReservation->reserved_for_order ?? '0.0000');
                    /** @var numeric-string $ownReserved */
                    $available = bcadd($available, $ownReserved, 4);
                    $media = $variant->product->primaryMedia;
                    $imageUrl = $media ? Storage::disk($media->disk)->url($media->path) : null;

                    return [
                        'id' => $variant->id,
                        'product_id' => $variant->product_id,
                        'name' => $variant->product->name.($variant->name ? ' · '.$variant->name : ''),
                        'sku' => $variant->sku ?: $variant->product->sku,
                        'price' => $variant->effectiveSellingPrice(),
                        'available' => $available,
                        'image_url' => $imageUrl,
                        'image_alt' => $media?->alt_text ?: $variant->product->name,
                        'inventory_owner' => $ownerType,
                    ];
                })
                ->filter()
                ->values();
        }

        return response()->json(['locations' => $locations, 'variants' => $variants]);
    }

    public function editOptions(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $request->merge([
            'order_id' => $order->id,
            'location_id' => $order->location_id,
        ]);

        return $this->options($request);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount_total' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'promotion_code' => ['nullable', 'string', 'max:60'],
            'channel' => ['nullable', Rule::in(['backoffice', 'website', 'storefront', 'mobile_app'])],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
        ]);
        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->whereKey($data['customer_id'])->where('status', 'active')->firstOrFail();
            $data['customer_name'] = $customer->name;
            $data['customer_email'] = $customer->email;
            $data['customer_phone'] = $customer->phone;
        }
        if (empty($data['customer_name'])) {
            throw ValidationException::withMessages(['customer_id' => 'Choose an active customer or enter a customer name.']);
        }
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        $deferInventoryReservation = ($data['channel'] ?? 'backoffice') === 'storefront';
        $order = $this->orders->create($data, $request->user(), $deferInventoryReservation);
        $status = $order->wasRecentlyCreated ? 201 : 200;
        $createdMessage = $deferInventoryReservation
            ? 'Storefront order created and is awaiting executive confirmation. Stock has not been reserved.'
            : 'Order created and stock reserved.';

        return response()->json([
            'message' => $status === 201 ? $createdMessage : 'Existing order request replayed.',
            'data' => $order,
        ], $status);
    }

    public function update(Request $request, SalesOrder $order): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:160'],
            'customer_email' => ['nullable', 'email', 'max:190'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'discount_total' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'promotion_code' => ['nullable', 'string', 'max:60'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_variant_id' => ['required', 'integer', 'distinct', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000', 'regex:/^\\d{1,14}(?:\\.\\d{1,4})?$/'],
        ]);

        if (! empty($data['customer_id'])) {
            $customer = Customer::query()->whereKey($data['customer_id'])->where('status', 'active')->firstOrFail();
            $data['customer_name'] = $customer->name;
            $data['customer_email'] = $customer->email;
            $data['customer_phone'] = $customer->phone;
        }
        if (empty($data['customer_name'])) {
            throw ValidationException::withMessages(['customer_id' => 'Choose an active customer or enter a customer name.']);
        }
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $updated = $this->orders->updatePending($order, $data, $request->user());

        return response()->json(['message' => 'Pending order updated and stock reservations refreshed.', 'data' => $updated]);
    }

    public function show(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertView($request->user(), (int) $order->location_id);
        $order->load(['location:id,name,code', 'creator:id,name', 'items.fulfillmentItems', 'statusHistory.actor:id,name', 'fulfillments.items.orderItem', 'shipments', 'riskAssessments', 'smsLogs', 'adjustments.actor:id,name', 'adjustments.settlements.actor:id,name', 'adjustments.settlements.transaction']);
        $order->items->each(function ($item): void {
            $done = $item->fulfillmentItems->sum(fn ($line) => (float) $line->quantity);
            $item->setAttribute('fulfilled_quantity', number_format($done, 4, '.', ''));
            $item->setAttribute('remaining_quantity', number_format(max(0, (float) $item->quantity - $done), 4, '.', ''));
        });

        return response()->json(['data' => $order]);
    }

    public function adjust(
        Request $request,
        SalesOrder $order,
        SalesOrderAdjustmentService $adjustments,
    ): JsonResponse {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'discount_total' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'delivery_charge' => ['sometimes', 'numeric', 'min:0', 'max:999999999999'],
            'items' => ['sometimes', 'array', 'min:1', 'max:100'],
            'items.*.id' => ['required', 'integer'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
        ]);
        if (! array_key_exists('discount_total', $data)
            && ! array_key_exists('delivery_charge', $data)
            && empty($data['items'])) {
            throw ValidationException::withMessages(['adjustment' => 'Provide at least one product price, discount or delivery charge change.']);
        }

        $adjustment = $adjustments->apply($order, $data, $request->user());

        return response()->json([
            'message' => 'Order adjustment applied and recorded.',
            'data' => $adjustment,
            'order' => $order->fresh(['items', 'adjustments.actor:id,name', 'adjustments.settlements.actor:id,name', 'adjustments.settlements.transaction']),
        ], 201);
    }

    public function settleAdjustment(
        Request $request,
        SalesOrder $order,
        SalesOrderAdjustment $adjustment,
        ActivityLogService $activityLog,
    ): JsonResponse {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $data = $request->validate([
            'settlement_type' => ['required', Rule::in(['collection', 'refund', 'customer_credit'])],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'financial_transaction_id' => ['nullable', 'integer', 'exists:financial_transactions,id'],
            'reference' => ['nullable', 'string', 'max:120'],
        ]);

        $settlement = DB::transaction(function () use ($request, $order, $adjustment, $data, $activityLog): SalesOrderAdjustmentSettlement {
            $lockedOrder = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            $lockedAdjustment = SalesOrderAdjustment::query()
                ->where('sales_order_id', $lockedOrder->id)
                ->lockForUpdate()->findOrFail($adjustment->id);

            $delta = (string) $lockedAdjustment->financial_delta;
            $type = $data['settlement_type'];
            if (bccomp($delta, '0.00', 2) > 0 && $type !== 'collection') {
                throw ValidationException::withMessages(['settlement_type' => 'An increased order balance can only be reconciled as a collection.']);
            }
            if (bccomp($delta, '0.00', 2) < 0 && ! in_array($type, ['refund', 'customer_credit'], true)) {
                throw ValidationException::withMessages(['settlement_type' => 'A reduced paid order can only be reconciled as a refund or customer credit.']);
            }
            if (bccomp($delta, '0.00', 2) === 0) {
                throw ValidationException::withMessages(['settlement_type' => 'This adjustment has no financial balance to reconcile.']);
            }

            $alreadySettled = (string) SalesOrderAdjustmentSettlement::query()
                ->where('sales_order_adjustment_id', $lockedAdjustment->id)->sum('amount');
            $remaining = bcsub(bccomp($delta, '0.00', 2) > 0 ? $delta : bcmul($delta, '-1', 2), $alreadySettled, 2);
            $amount = number_format((float) $data['amount'], 2, '.', '');
            if (bccomp($amount, '0.00', 2) <= 0 || bccomp($amount, $remaining, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Settlement amount must be positive and cannot exceed the outstanding adjustment balance of '.$remaining.'.']);
            }

            $transaction = null;
            if ($type === 'customer_credit') {
                if (! empty($data['financial_transaction_id'])) {
                    throw ValidationException::withMessages(['financial_transaction_id' => 'A customer credit note must not reference a cash transaction.']);
                }
            } else {
                if (empty($data['financial_transaction_id'])) {
                    throw ValidationException::withMessages(['financial_transaction_id' => 'Select the posted financial transaction that records this collection or refund.']);
                }
                $transaction = FinancialTransaction::query()->lockForUpdate()->findOrFail($data['financial_transaction_id']);
                $expectedType = $type === 'collection' ? 'customer_receipt' : 'expense';
                if ($transaction->type !== $expectedType
                    || (int) $transaction->customer_id !== (int) $lockedOrder->customer_id
                    || (int) $transaction->location_id !== (int) $lockedOrder->location_id) {
                    throw ValidationException::withMessages(['financial_transaction_id' => 'The financial transaction must match this order customer and location.']);
                }
                if (bccomp((string) $transaction->amount, $amount, 2) < 0) {
                    throw ValidationException::withMessages(['financial_transaction_id' => 'The financial transaction amount is smaller than this settlement.']);
                }
                if ($type === 'collection') {
                    if (! in_array($lockedOrder->status, ['confirmed', 'processing', 'fulfilled', 'completed'], true)) {
                        throw ValidationException::withMessages(['order' => 'Confirm the order before allocating a customer receipt.']);
                    }
                    $paymentAllocated = (string) DB::table('payment_allocations')->where('financial_transaction_id', $transaction->id)->sum('amount');
                    $orderPaid = (string) DB::table('payment_allocations')->where('sales_order_id', $lockedOrder->id)->sum('amount');
                    $paymentRemaining = bcsub((string) $transaction->amount, $paymentAllocated, 2);
                    $orderDue = bcsub((string) $lockedOrder->grand_total, $orderPaid, 2);
                    if (bccomp($amount, $paymentRemaining, 2) > 0 || bccomp($amount, $orderDue, 2) > 0) {
                        throw ValidationException::withMessages(['amount' => 'Collection exceeds the unallocated receipt amount or the order balance due.']);
                    }
                    DB::table('payment_allocations')->insert([
                        'financial_transaction_id' => $transaction->id,
                        'sales_order_id' => $lockedOrder->id,
                        'amount' => $amount,
                        'allocated_by' => $request->user()->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $record = SalesOrderAdjustmentSettlement::query()->create([
                'sales_order_adjustment_id' => $lockedAdjustment->id,
                'settlement_type' => $type,
                'amount' => $amount,
                'financial_transaction_id' => $transaction?->id,
                'reference' => $data['reference'] ?? ($type === 'customer_credit' ? $lockedAdjustment->adjustment_number.'-CREDIT' : $transaction?->transaction_number),
                'created_by' => $request->user()->id,
                'created_at' => now(),
            ]);
            $activityLog->record(
                'reconciled', 'orders',
                'Reconciled '.$type.' of '.$amount.' for '.$lockedAdjustment->adjustment_number,
                $record,
                null,
                ['settlement_type' => $type, 'amount' => $amount, 'financial_transaction_id' => $transaction?->id],
                ['remaining_before' => $remaining],
                $request->user(),
            );

            return $record->load(['actor:id,name', 'transaction']);
        }, 3);

        return response()->json(['message' => 'Adjustment settlement recorded.', 'data' => $settlement], 201);
    }

    public function confirm(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $assessment = $order->riskAssessments()->latest('id')->first();
        if ($assessment && in_array($assessment->decision, ['manual_review', 'reject'], true)) {
            throw ValidationException::withMessages(['risk' => 'This order requires an approved risk review before confirmation.']);
        }
        if ($assessment && $assessment->decision === 'verify' && ! SalesOrderPhoneVerification::query()->where('sales_order_id', $order->id)->whereNotNull('verified_at')->exists()) {
            throw ValidationException::withMessages(['risk' => 'Verify the customer phone before confirming this order.']);
        }
        $wasPending = $order->status === 'pending';
        $updated = $this->orders->confirm($order, $request->user());
        if ($wasPending && $updated->status === 'confirmed' && $updated->customer_phone) {
            $this->sms->sendOrderEvent($updated, 'confirmed');
        }

        return response()->json(['message' => 'Order confirmed.', 'data' => $updated]);
    }

    public function process(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $updated = $this->orders->startProcessing($order, $request->user());

        return response()->json(['message' => 'Order moved to processing.', 'data' => $updated]);
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
