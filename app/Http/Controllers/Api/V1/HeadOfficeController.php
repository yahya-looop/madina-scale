<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class HeadOfficeController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorization,
        private readonly LocationInventoryAccessService $locationAccess,
    ) {}

    public function health(Request $request): JsonResponse
    {
        return $this->respond($request, [
            'service' => 'madina-scale',
            'api_version' => 'v1',
            'status' => 'ready',
            'server_time' => now()->toISOString(),
            'capabilities' => ['catalog.read', 'inventory.read', 'orders.read', 'customers.read'],
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $user = $this->authorizeScope($request, 'head-office:catalog:read', 'system.products.view');
        $pageSize = $this->pageSize($request);
        $query = Product::query()->where('status', 'active')
            ->with(['category:id,name', 'brand:id,name', 'unit:id,name', 'activeVariants:id,product_id,sku,barcode,selling_price,status'])
            ->orderBy('id');

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $this->date($request, 'updated_since'));
        }

        $rows = $query->paginate($pageSize)->withQueryString();

        return $this->respond($request, [
            'data' => $rows->getCollection()->map(fn (Product $p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku,
                'barcode' => $p->barcode, 'model_number' => $p->model_number,
                'product_type' => $p->product_type, 'status' => $p->status,
                'category' => $p->category?->name, 'brand' => $p->brand?->name,
                'unit' => $p->unit?->name,
                'variants' => $p->activeVariants->map(fn ($v) => [
                    'id' => $v->id, 'sku' => $v->sku, 'barcode' => $v->barcode,
                    'selling_price' => $v->selling_price,
                ])->values(),
                'updated_at' => $p->updated_at?->toISOString(),
            ])->values(),
            'meta' => $this->pagination($rows),
        ]);
    }

    public function inventory(Request $request): JsonResponse
    {
        $user = $this->authorizeScope($request, 'head-office:inventory:read', 'system.inventory.view');
        $pageSize = $this->pageSize($request);
        $query = InventoryStock::query()->with([
            'product:id,name,sku',
            'variant:id,product_id,sku,barcode',
            'location:id,name,code',
        ]);
        $this->locationAccess->scopeViewable($query, $user, 'location_id');

        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }
        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $this->date($request, 'updated_since'));
        }

        $rows = $query->orderBy('id')->paginate($pageSize)->withQueryString();

        return $this->respond($request, [
            'data' => $rows->getCollection()->map(fn (InventoryStock $s) => [
                'id' => $s->id, 'product_id' => $s->product_id,
                'variant_id' => $s->product_variant_id,
                'sku' => $s->variant?->sku ?? $s->product?->sku,
                'product_name' => $s->product?->name,
                'location' => ['id' => $s->location_id, 'name' => $s->location?->name, 'code' => $s->location?->code],
                'on_hand' => (string) $s->quantity,
                'reserved' => (string) $s->reserved_quantity,
                'available' => (string) $s->available_quantity,
                'updated_at' => $s->updated_at?->toISOString(),
            ])->values(),
            'meta' => $this->pagination($rows),
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        $this->authorizeScope($request, 'head-office:orders:read', 'system.orders.view');
        $pageSize = $this->pageSize($request);
        $query = SalesOrder::query()->with('location:id,name,code')
            ->select([
                'id', 'order_number', 'status', 'channel', 'location_id', 'customer_id',
                'currency', 'subtotal', 'discount_total', 'tax_total', 'grand_total',
                'created_at', 'updated_at', 'confirmed_at', 'cancelled_at',
            ])->orderBy('id');

        $this->locationAccess->scopeViewable($query, $request->user(), 'location_id');
        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $this->date($request, 'updated_since'));
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', (int) $request->input('location_id'));
        }
        $rows = $query->paginate($pageSize)->withQueryString();

        return $this->respond($request, [
            'data' => $rows->getCollection()->map(fn (SalesOrder $o) => [
                'id' => $o->id, 'order_number' => $o->order_number,
                'status' => $o->status, 'channel' => $o->channel,
                'customer_id' => $o->customer_id,
                'location' => ['id' => $o->location_id, 'name' => $o->location?->name, 'code' => $o->location?->code],
                'currency' => $o->currency, 'subtotal' => $o->subtotal,
                'discount_total' => $o->discount_total, 'tax_total' => $o->tax_total,
                'grand_total' => $o->grand_total,
                'created_at' => $o->created_at?->toISOString(),
                'updated_at' => $o->updated_at?->toISOString(),
                'confirmed_at' => $o->confirmed_at?->toISOString(),
                'cancelled_at' => $o->cancelled_at?->toISOString(),
            ])->values(),
            'meta' => $this->pagination($rows),
        ]);
    }

    public function customers(Request $request): JsonResponse
    {
        $this->authorizeScope($request, 'head-office:customers:read', 'system.customers.view');
        $pageSize = $this->pageSize($request);
        $query = Customer::query()->select([
            'id', 'customer_code', 'name', 'company_name', 'status', 'created_at', 'updated_at',
        ])->orderBy('id');

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $this->date($request, 'updated_since'));
        }
        $rows = $query->paginate($pageSize)->withQueryString();

        return $this->respond($request, [
            'data' => $rows->getCollection()->map(fn (Customer $c) => [
                'id' => $c->id, 'customer_code' => $c->customer_code,
                'name' => $c->name, 'company_name' => $c->company_name,
                'status' => $c->status, 'created_at' => $c->created_at?->toISOString(),
                'updated_at' => $c->updated_at?->toISOString(),
            ])->values(),
            'meta' => $this->pagination($rows),
        ]);
    }

    private function authorizeScope(Request $request, string $ability, string $permission): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($request->user()->currentAccessToken()?->can($ability), 403, 'API token scope is missing.');
        abort_unless($this->authorization->can($user, $permission), 403, 'The token owner lacks the required ERP permission.');

        return $user;
    }

    private function pageSize(Request $request): int
    {
        $size = filter_var($request->query('per_page', 50), FILTER_VALIDATE_INT);
        if ($size === false || $size < 1 || $size > 100) {
            throw ValidationException::withMessages(['per_page' => 'per_page must be between 1 and 100.']);
        }

        return $size;
    }

    private function date(Request $request, string $key): string
    {
        try {
            return Carbon::parse($request->query($key))->toDateTimeString();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$key => 'A valid date/time is required.']);
        }
    }

    private function pagination($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'next_page_url' => $paginator->nextPageUrl(),
        ];
    }

    private function respond(Request $request, array $payload): JsonResponse
    {
        $requestId = (string) ($request->attributes->get('request_id') ?: Str::uuid());

        return response()->json([
            'meta' => ['api_version' => 'v1', 'request_id' => $requestId, 'generated_at' => now()->toISOString()],
            ...$payload,
        ])->header('X-Request-ID', $requestId);
    }
}
