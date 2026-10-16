<?php

namespace App\Http\Controllers;

use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventoryService,
    ) {
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
                'variant:id,product_id,name,sku,status',
                'location:id,name,code,type',
            ])
            ->orderBy('location_id')
            ->orderBy('id');

        foreach (['location_id', 'product_id', 'product_variant_id'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        return response()->json(
            $query->paginate($filters['per_page'] ?? 25)
        );
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
                'variant:id,product_id,name,sku',
                'location:id,name,code',
                'creator:id,name',
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

        return response()->json(
            $query->paginate($filters['per_page'] ?? 25)
        );
    }

    public function receive(Request $request): JsonResponse
    {
        $data = $this->validateMovement($request, true);

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

        $result = $this->inventoryService->transfer(
            $data,
            $request->user(),
        );

        return response()->json([
            'message' => 'Stock transferred successfully.',
            'data' => $result,
        ]);
    }

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
