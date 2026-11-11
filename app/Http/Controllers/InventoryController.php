<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Services\InventoryService;
use App\Services\InventoryTransferService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
        private readonly InventoryTransferService $inventoryTransferService,
        private readonly LocationInventoryAccessService $locationAccess,
    ) {}

    /**
     * List active locations available to the authenticated inventory operator.
     *
     * The response is scoped by location assignments and includes each user's
     * access level so the UI can offer only operations they can perform.
     */
    public function locations(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        return response()->json([
            'data' => $this->locationAccess->viewableLocations($user),
        ]);
    }

    public function stocks(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoryStock::query()
            ->with([
                'product:id,name,sku,product_type',
                'product.primaryMedia:id,product_id,path,alt_text,is_primary',
                'variant:id,product_id,name,sku,status',
                'location:id,name,code,type',
            ])
            ->orderBy('location_id')
            ->orderBy('id');

        if (isset($filters['location_id'])) {
            $this->locationAccess->assertView($request->user(), (int) $filters['location_id']);
        } else {
            $this->locationAccess->scopeViewable($query, $request->user());
        }

        foreach (['location_id', 'product_id', 'product_variant_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $paginator = $query->paginate($filters['per_page'] ?? 25);
        $this->attachLatestMovementAudit($paginator->getCollection());

        return response()->json($paginator);
    }

    public function lowStock(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoryStock::query()
            ->with([
                'product:id,name,sku,product_type',
                'product.primaryMedia:id,product_id,path,alt_text,is_primary',
                'variant:id,product_id,name,sku,status',
                'location:id,name,code,type',
            ])
            ->where('low_stock_threshold', '>', 0)
            ->whereRaw(
                '(quantity - reserved_quantity) <= low_stock_threshold'
            )
            ->orderBy('location_id')
            ->orderBy('id');

        if (isset($filters['location_id'])) {
            $this->locationAccess->assertView($request->user(), (int) $filters['location_id']);
        } else {
            $this->locationAccess->scopeViewable($query, $request->user());
        }

        foreach (['location_id', 'product_id', 'product_variant_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        $paginator = $query->paginate($filters['per_page'] ?? 25);

        $paginator->getCollection()->transform(
            function (InventoryStock $stock): array {
                return array_merge($stock->toArray(), [
                    'available_quantity' => $stock->available_quantity,
                    'is_low_stock' => $stock->is_low_stock,
                ]);
            }
        );

        return response()->json($paginator);
    }

    public function updateThreshold(
        Request $request,
        InventoryStock $stock,
    ): JsonResponse {
        $this->locationAccess->assertManage($request->user(), (int) $stock->location_id);
        $data = $request->validate([
            'low_stock_threshold' => [
                'required',
                'integer',
                'min:20',
                'max:99999999999999',
            ],
        ]);

        $stock->low_stock_threshold = $data['low_stock_threshold'];
        $stock->save();

        $stock->refresh()->load([
            'product:id,name,sku,product_type',
            'product.primaryMedia:id,product_id,path,alt_text,is_primary',
            'variant:id,product_id,name,sku,status',
            'location:id,name,code,type',
        ]);

        return response()->json([
            'message' => 'Low-stock threshold updated successfully.',
            'data' => array_merge($stock->toArray(), [
                'available_quantity' => $stock->available_quantity,
                'is_low_stock' => $stock->is_low_stock,
            ]),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'location_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'movement_type' => [
                'nullable',
                Rule::in([
                    'opening',
                    'receipt',
                    'issue',
                    'order_fulfillment',
                    'adjustment',
                    'transfer_in',
                    'transfer_out',
                    'reversal',
                ]),
            ],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = StockMovement::query()
            ->with([
                'product:id,name,sku,product_type',
                'product.primaryMedia:id,product_id,path,alt_text,is_primary',
                'variant:id,product_id,name,sku',
                'location:id,name,code',
                'creator:id,name,email',
            ])
            ->when(
                isset($filters['location_id']),
                fn ($q) => $q->where('location_id', $filters['location_id']),
            )
            ->when(
                isset($filters['product_id']),
                fn ($q) => $q->where('product_id', $filters['product_id']),
            )
            ->when(
                isset($filters['product_variant_id']),
                fn ($q) => $q->where(
                    'product_variant_id',
                    $filters['product_variant_id'],
                ),
            )
            ->when(
                isset($filters['movement_type']),
                fn ($q) => $q->where('movement_type', $filters['movement_type']),
            )
            ->when(
                isset($filters['from']),
                fn ($q) => $q->whereDate('created_at', '>=', $filters['from']),
            )
            ->when(
                isset($filters['to']),
                fn ($q) => $q->whereDate('created_at', '<=', $filters['to']),
            )
            ->orderByDesc('id');

        if (isset($filters['location_id'])) {
            $this->locationAccess->assertView($request->user(), (int) $filters['location_id']);
        } else {
            $this->locationAccess->scopeViewable($query, $request->user());
        }

        return response()->json(
            $query->paginate($filters['per_page'] ?? 25)
        );
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $this->validateMovement($request, true);
        $this->locationAccess->assertReceive($request->user(), (int) $data['location_id']);

        // Capture request metadata before the immutable ledger row is inserted.
        // Never update a stock movement after creation.
        $data['ip_address'] = $request->ip();

        $movement = $this->inventoryService->receive(
            $data,
            $request->user(),
        );

        return response()->json([
            'message' => 'Stock received successfully.',
            'data' => $movement->load([
                'product',
                'variant',
                'location',
                'creator',
            ]),
        ], 201);
    }

    public function issue(Request $request): JsonResponse
    {
        $data = $this->validateMovement($request, true);
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);

        // Capture request metadata before the immutable ledger row is inserted.
        $data['ip_address'] = $request->ip();

        $movement = $this->inventoryService->issue(
            $data,
            $request->user(),
        );

        return response()->json([
            'message' => 'Stock issued successfully.',
            'data' => $movement->load([
                'product',
                'variant',
                'location',
                'creator',
            ]),
        ]);
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $this->validateMovement($request, true);
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);

        // Capture request metadata before the immutable ledger row is inserted.
        $data['ip_address'] = $request->ip();

        $movement = $this->inventoryService->adjust(
            $data,
            $request->user(),
        );

        return response()->json([
            'message' => 'Stock adjusted successfully.',
            'data' => $movement->load([
                'product',
                'variant',
                'location',
                'creator',
            ]),
        ]);
    }

    /**
     * Transfer stock only for a product/variant that is currently low at the destination.
     * This server-side check prevents clients from bypassing the Low Stock-only Restock UI.
     */
    public function restock(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1', 'required_without:product_variant_id', 'prohibits:product_variant_id'],
            'product_variant_id' => ['nullable', 'integer', 'min:1', 'required_without:product_id', 'prohibits:product_id'],
            'source_location_id' => ['required', 'integer', 'min:1', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'uuid'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Dispatch authorization is based on the source location. The destination
        // receiver is authorized independently when accepting the transfer.
        $this->locationAccess->assertManage($request->user(), (int) $data['source_location_id']);

        $stockQuery = InventoryStock::query()
            ->where('location_id', $data['destination_location_id'])
            ->where('low_stock_threshold', '>', 0)
            ->whereRaw('(quantity - reserved_quantity) <= low_stock_threshold');
        if (isset($data['product_variant_id'])) {
            $stockQuery->where('product_variant_id', $data['product_variant_id'])->whereNull('product_id');
        } else {
            $stockQuery->where('product_id', $data['product_id'])->whereNull('product_variant_id');
        }
        if (! $stockQuery->exists()) {
            return response()->json([
                'message' => 'Restock is only available for an item that is currently low in stock at the selected destination.',
                'errors' => ['stock_id' => ['Choose a current low-stock item at the destination location.']],
            ], 422);
        }

        $transfer = $this->inventoryTransferService->dispatch($data, $request->user(), $request->ip());

        return response()->json([
            'message' => 'Restock transfer dispatched and is awaiting destination confirmation.',
            'data' => $transfer,
        ], 201);
    }

    public function transfer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1', 'required_without:product_variant_id', 'prohibits:product_variant_id'],
            'product_variant_id' => ['nullable', 'integer', 'min:1', 'required_without:product_id', 'prohibits:product_id'],
            'source_location_id' => ['required', 'integer', 'min:1', 'different:destination_location_id'],
            'destination_location_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'uuid'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Dispatch authorization is based on the source location. The destination
        // receiver is authorized independently when accepting the transfer.
        $this->locationAccess->assertManage($request->user(), (int) $data['source_location_id']);
        $transfer = $this->inventoryTransferService->dispatch($data, $request->user(), $request->ip());

        return response()->json([
            'message' => 'Transfer dispatched and is awaiting destination confirmation.',
            'data' => $transfer,
        ], 201);
    }

    /**
     * @param  Collection<int, InventoryStock>  $stocks
     */
    private function attachLatestMovementAudit(Collection $stocks): void
    {
        if ($stocks->isEmpty()) {
            return;
        }

        $productIds = $stocks->pluck('product_id')->filter()->unique()->values();
        $variantIds = $stocks->pluck('product_variant_id')->filter()->unique()->values();
        $locationIds = $stocks->pluck('location_id')->filter()->unique()->values();

        if ($locationIds->isEmpty() || ($productIds->isEmpty() && $variantIds->isEmpty())) {
            return;
        }

        // Aggregate in SQL first: never hydrate an unbounded movement history
        // just to select one latest audit row for each stock record.
        $latestMovementIds = StockMovement::query()
            ->selectRaw('MAX(id) AS latest_id')
            ->whereIn('location_id', $locationIds)
            ->where(function ($query) use ($productIds, $variantIds): void {
                if ($productIds->isNotEmpty()) {
                    $query->where(function ($simple) use ($productIds): void {
                        $simple->whereIn('product_id', $productIds)
                            ->whereNull('product_variant_id');
                    });
                }

                if ($variantIds->isNotEmpty()) {
                    $variantFilter = function ($variant) use ($variantIds): void {
                        $variant->whereIn('product_variant_id', $variantIds)
                            ->whereNull('product_id');
                    };

                    if ($productIds->isNotEmpty()) {
                        $query->orWhere($variantFilter);
                    } else {
                        $query->where($variantFilter);
                    }
                }
            })
            ->groupBy('product_id', 'product_variant_id', 'location_id')
            ->pluck('latest_id');

        $latest = StockMovement::query()
            ->with('creator:id,name,email')
            ->whereIn('id', $latestMovementIds)
            ->get()
            ->keyBy(fn (StockMovement $movement): string => implode(':', [
                $movement->product_id ?? 0,
                $movement->product_variant_id ?? 0,
                $movement->location_id,
            ]));

        foreach ($stocks as $stock) {
            $key = implode(':', [
                $stock->product_id ?? 0,
                $stock->product_variant_id ?? 0,
                $stock->location_id,
            ]);
            $stock->setAttribute('latest_movement', $latest->get($key));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMovement(
        Request $request,
        bool $allowIdempotencyKey,
    ): array {
        return $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                'min:1',
                'required_without:product_variant_id',
                'prohibits:product_variant_id',
            ],
            'product_variant_id' => [
                'nullable',
                'integer',
                'min:1',
                'required_without:product_id',
                'prohibits:product_id',
            ],
            'location_id' => ['required', 'integer', 'min:1'],
            'quantity' => [
                'required',
                'numeric',
                'not_in:0',
                'regex:/^-?\d{1,14}(?:\.\d{1,4})?$/',
            ],
            'reason' => ['nullable', 'string', 'max:255'],
            'reference_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => [
                $allowIdempotencyKey ? 'nullable' : 'prohibited',
                'uuid',
            ],
            'metadata' => ['nullable', 'array'],
        ]);
    }
}
