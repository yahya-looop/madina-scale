<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransfer;
use App\Services\AuthorizationService;
use App\Services\InventoryTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryTransferController extends Controller
{
    public function __construct(private readonly InventoryTransferService $transfers, private readonly AuthorizationService $authorization) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'direction' => ['nullable', Rule::in(['incoming', 'outgoing', 'all'])],
            'status' => ['nullable', Rule::in(['in_transit', 'partially_received', 'received', 'rejected', 'cancelled', 'discrepancy'])],
            'location_id' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = InventoryTransfer::query()->with([
            'product:id,name,sku,product_type', 'variant:id,product_id,name,sku',
            'sourceLocation:id,name,code', 'destinationLocation:id,name,code',
            'dispatcher:id,name,email', 'receiver:id,name,email', 'resolver:id,name,email',
        ])->latest('id');

        $direction = $filters['direction'] ?? 'all';
        $user = $request->user();
        $isLocationManager = $this->authorization->can($user, 'system.locations.manage');
        $assignedLocationIds = $user->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $manageableLocationIds = $user->locations()->whereIn('location_user.access_level', ['manage'])->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $receivableLocationIds = $user->locations()->whereIn('location_user.access_level', ['receive', 'manage'])->pluck('locations.id')->map(fn ($id) => (int) $id)->all();

        if (isset($filters['location_id']) && ! $isLocationManager && ! in_array((int) $filters['location_id'], $assignedLocationIds, true)) {
            abort(403, 'You are not assigned to the requested location.');
        }

        if (! $isLocationManager) {
            $query->where(function ($scope) use ($assignedLocationIds, $direction): void {
                if ($direction === 'incoming') {
                    $scope->whereIn('destination_location_id', $receivableLocationIds);
                } elseif ($direction === 'outgoing') {
                    $scope->whereIn('source_location_id', $manageableLocationIds);
                } else {
                    $scope->whereIn('source_location_id', $assignedLocationIds)
                        ->orWhereIn('destination_location_id', $assignedLocationIds);
                }
            });
        }

        if ($direction === 'incoming') {
            if (isset($filters['location_id'])) {
                $query->where('destination_location_id', $filters['location_id']);
            }
        } elseif ($direction === 'outgoing') {
            if (isset($filters['location_id'])) {
                $query->where('source_location_id', $filters['location_id']);
            }
        } elseif (isset($filters['location_id'])) {
            $query->where(fn ($q) => $q->where('source_location_id', $filters['location_id'])->orWhere('destination_location_id', $filters['location_id']));
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return response()->json($query->paginate($filters['per_page'] ?? 20));
    }

    public function counts(Request $request): JsonResponse
    {
        $locationId = $request->validate(['location_id' => ['nullable', 'integer', 'min:1']])['location_id'] ?? null;
        $user = $request->user();
        $isLocationManager = $this->authorization->can($user, 'system.locations.manage');
        $assignedLocationIds = $user->locations()->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $manageableLocationIds = $user->locations()->where('location_user.access_level', 'manage')->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        $receivableLocationIds = $user->locations()->whereIn('location_user.access_level', ['receive', 'manage'])->pluck('locations.id')->map(fn ($id) => (int) $id)->all();
        if ($locationId && ! $isLocationManager && ! in_array((int) $locationId, $assignedLocationIds, true)) {
            abort(403, 'You are not assigned to the requested location.');
        }

        $incoming = InventoryTransfer::query()->whereIn('status', ['in_transit', 'partially_received']);
        $outgoing = InventoryTransfer::query()->whereIn('status', ['in_transit', 'partially_received']);
        $discrepancies = InventoryTransfer::query()->where('status', 'discrepancy');
        if ($isLocationManager) {
            if ($locationId) {
                $incoming->where('destination_location_id', $locationId);
                $outgoing->where('source_location_id', $locationId);
                $discrepancies->where(fn ($sub) => $sub->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId));
            }
        } else {
            $incoming->whereIn('destination_location_id', $receivableLocationIds);
            $outgoing->whereIn('source_location_id', $manageableLocationIds);
            $discrepancies->where(fn ($sub) => $sub->whereIn('source_location_id', $assignedLocationIds)->orWhereIn('destination_location_id', $assignedLocationIds));
            if ($locationId) {
                $incoming->where('destination_location_id', $locationId);
                $outgoing->where('source_location_id', $locationId);
                $discrepancies->where(fn ($sub) => $sub->where('source_location_id', $locationId)->orWhere('destination_location_id', $locationId));
            }
        }

        return response()->json([
            'incoming_pending' => $incoming->count(),
            'outgoing_pending' => $outgoing->count(),
            'discrepancies' => $discrepancies->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
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
        $transfer = $this->transfers->dispatch($data, $request->user(), $request->ip());

        return response()->json(['message' => 'Transfer dispatched and is awaiting destination confirmation.', 'data' => $transfer], 201);
    }

    public function accept(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        $data = $request->validate([
            'quantity_received' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,14}(?:\.\d{1,4})?$/'],
            'recipient_note' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        $updated = $this->transfers->receive($transfer, $data, $request->user(), $request->ip());

        return response()->json(['message' => $updated->status === 'received' ? 'Transfer received successfully.' : 'Partial receipt recorded.', 'data' => $updated]);
    }

    public function reject(Request $request, InventoryTransfer $transfer): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:2000']]);
        $updated = $this->transfers->reject($transfer, $data['reason'], $request->user());

        return response()->json(['message' => 'Transfer flagged for discrepancy resolution. Stock was not silently returned to the source.', 'data' => $updated]);
    }
}
