<?php

namespace App\Http\Controllers;

use App\Models\InventoryReservation;
use App\Services\InventoryReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryReservationController extends Controller
{
    public function __construct(private readonly InventoryReservationService $reservations) {}

    public function index(Request $request): JsonResponse
    {
        $this->reservations->expireDue();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'released', 'cancelled', 'fulfilled', 'expired'])],
            'purpose' => ['nullable', Rule::in(['customer_order', 'internal_use'])],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = InventoryReservation::query()->with([
            'product:id,name,sku,product_type', 'variant:id,product_id,name,sku',
            'location:id,name,code', 'creator:id,name,email', 'resolver:id,name,email',
        ])->orderByDesc('id');
        foreach (['status', 'purpose', 'location_id', 'product_id', 'product_variant_id'] as $key) {
            if (isset($filters[$key])) $query->where($key, $filters[$key]);
        }
        return response()->json($query->paginate($filters['per_page'] ?? 25));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'product_id' => ['nullable', 'integer', 'min:1', 'required_without:product_variant_id', 'prohibits:product_variant_id'],
            'product_variant_id' => ['nullable', 'integer', 'min:1', 'required_without:product_id', 'prohibits:product_id'],
            'location_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999999999'],
            'purpose' => ['required', Rule::in(['customer_order', 'internal_use'])],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $reservation = $this->reservations->reserve($data, $request->user());
        return response()->json(['message' => 'Stock reserved successfully.', 'data' => $reservation->load(['product:id,name,sku', 'variant:id,product_id,name,sku', 'location:id,name,code', 'creator:id,name,email'])], 201);
    }

    public function release(Request $request, InventoryReservation $reservation): JsonResponse
    {
        return $this->resolve($request, $reservation, 'release');
    }

    public function cancel(Request $request, InventoryReservation $reservation): JsonResponse
    {
        return $this->resolve($request, $reservation, 'cancel');
    }

    public function fulfill(Request $request, InventoryReservation $reservation): JsonResponse
    {
        return $this->resolve($request, $reservation, 'fulfill');
    }

    private function resolve(Request $request, InventoryReservation $reservation, string $action): JsonResponse
    {
        $updated = $this->reservations->resolve($reservation, $action, $request->user());
        return response()->json(['message' => 'Reservation ' . $updated->status . ' successfully.', 'data' => $updated->load(['product:id,name,sku', 'variant:id,product_id,name,sku', 'location:id,name,code', 'creator:id,name,email', 'resolver:id,name,email'])]);
    }
}
