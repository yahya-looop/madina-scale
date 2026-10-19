<?php

namespace App\Http\Controllers;

use App\Models\InventoryReservation;
use App\Models\InventoryReservationPurpose;
use App\Services\InventoryReservationService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryReservationController extends Controller
{
    public function __construct(
        private readonly InventoryReservationService $reservations,
        private readonly LocationInventoryAccessService $locationAccess,
    ) {}

    public function purposes(): JsonResponse
    {
        return response()->json(['data' => InventoryReservationPurpose::query()
            ->where('is_active', true)->orderBy('sort_order')->orderBy('label')->get(['id', 'key', 'label', 'is_active', 'sort_order'])]);
    }

    public function storePurpose(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:24', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:inventory_reservation_purposes,key'],
            'label' => ['required', 'string', 'max:80'],
        ]);
        $purpose = InventoryReservationPurpose::query()->create([
            'key' => $data['key'], 'label' => trim($data['label']), 'is_active' => true,
            'sort_order' => (int) InventoryReservationPurpose::query()->max('sort_order') + 10,
        ]);
        return response()->json(['message' => 'Reservation purpose added successfully.', 'data' => $purpose], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $this->reservations->expireDue();
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'released', 'cancelled', 'fulfilled', 'expired'])],
            'purpose' => ['nullable', 'string', 'max:24'],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'product_id' => ['nullable', 'integer', 'min:1'],
            'product_variant_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $query = InventoryReservation::query()->with([
            'product:id,name,sku,product_type', 'variant:id,product_id,name,sku',
            'location:id,name,code', 'creator:id,name,email', 'resolver:id,name,email',
        ])->orderByDesc('id');
        if (isset($filters['location_id'])) {
            $this->locationAccess->assertView($request->user(), (int) $filters['location_id']);
        } else {
            $this->locationAccess->scopeViewable($query, $request->user());
        }
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
            'purpose' => ['required', 'string', 'max:24', Rule::exists('inventory_reservation_purposes', 'key')->where('is_active', true)],
            'reference_type' => ['nullable', 'string', 'max:80'],
            'reference_id' => ['nullable', 'string', 'max:120'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);
        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
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
        $this->locationAccess->assertManage($request->user(), (int) $reservation->location_id);
        $updated = $this->reservations->resolve($reservation, $action, $request->user());
        return response()->json(['message' => 'Reservation ' . $updated->status . ' successfully.', 'data' => $updated->load(['product:id,name,sku', 'variant:id,product_id,name,sku', 'location:id,name,code', 'creator:id,name,email', 'resolver:id,name,email'])]);
    }
}
