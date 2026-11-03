<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferReceipt;
use App\Models\Location;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\InventoryTransferNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function dispatch(array $data, User $actor, ?string $ipAddress = null): InventoryTransfer
    {
        $quantity = $this->quantity($data['quantity'] ?? null);
        $sourceId = (int) $data['source_location_id'];
        $destinationId = (int) $data['destination_location_id'];
        $isVariant = isset($data['product_variant_id']);
        $ownerId = (int) ($data[$isVariant ? 'product_variant_id' : 'product_id']);

        $this->assertLocationAccess($actor, $sourceId, ['manage'], 'source_location_id');

        $transfer = DB::transaction(function () use ($data, $actor, $ipAddress, $quantity, $sourceId, $destinationId, $isVariant, $ownerId): InventoryTransfer {
            $idempotencyKey = $data['idempotency_key'] ?? null;
            if ($idempotencyKey !== null && $idempotencyKey !== '') {
                // Serialize dispatches using the same key before checking for an existing transfer.
                // The unique index remains the final database-level safety net.
                DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', [$idempotencyKey]);
                $existing = $this->findIdempotentTransfer(
                    $idempotencyKey,
                    $sourceId,
                    $destinationId,
                    $ownerId,
                    $isVariant,
                    $quantity,
                    $data,
                );
                if ($existing !== null) {
                    return $existing->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher']);
                }
            }

            $locations = Location::query()->whereIn('id', [$sourceId, $destinationId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($locations->count() !== 2 || $locations->contains(fn (Location $location) => ! $location->is_active)) {
                throw ValidationException::withMessages(['destination_location_id' => 'Both locations must exist and be active.']);
            }

            $hasReceiver = User::query()->where('status', 'active')
                ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
                ->whereHas('locations', fn ($query) => $query
                    ->where('locations.id', $destinationId)
                    ->whereIn('location_user.access_level', ['receive', 'manage']))
                ->exists();
            if (! $hasReceiver) {
                throw ValidationException::withMessages(['destination_location_id' => 'Assign at least one active inventory-transfer user with receive access to the destination before dispatch.']);
            }

            $ownerQuery = $isVariant
                ? DB::table('product_variants')->where('id', $ownerId)
                : DB::table('products')->where('id', $ownerId);
            if (! $ownerQuery->exists()) {
                throw ValidationException::withMessages([$isVariant ? 'product_variant_id' : 'product_id' => 'The selected inventory item does not exist.']);
            }

            $stockQuery = InventoryStock::query()->where('location_id', $sourceId);
            $stockQuery = $isVariant
                ? $stockQuery->where('product_variant_id', $ownerId)->whereNull('product_id')
                : $stockQuery->where('product_id', $ownerId)->whereNull('product_variant_id');
            $stock = $stockQuery->lockForUpdate()->first();
            if (! $stock) {
                throw ValidationException::withMessages(['quantity' => 'No stock record exists at the source location.']);
            }

            $after = bcsub((string) $stock->quantity, $quantity, 4);
            if (bccomp($after, (string) $stock->reserved_quantity, 4) < 0) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient available stock. Reserved stock cannot be dispatched.']);
            }

            $transferUuid = (string) Str::uuid();
            $transfer = InventoryTransfer::query()->create([
                'transfer_uuid' => $transferUuid,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'product_id' => $isVariant ? null : $ownerId,
                'product_variant_id' => $isVariant ? $ownerId : null,
                'source_location_id' => $sourceId,
                'destination_location_id' => $destinationId,
                'quantity_sent' => $quantity,
                'quantity_received' => '0',
                'status' => 'in_transit',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => isset($data['reference_id']) ? (string) $data['reference_id'] : null,
                'reason' => $data['reason'] ?? null,
                'dispatched_by' => $actor->id,
                'dispatched_at' => now(),
                'metadata' => $data['metadata'] ?? null,
            ]);

            $before = (string) $stock->quantity;
            $stock->quantity = $after;
            $stock->save();

            StockMovement::query()->create([
                'transfer_group_id' => $transferUuid,
                'product_id' => $isVariant ? null : $ownerId,
                'product_variant_id' => $isVariant ? $ownerId : null,
                'location_id' => $sourceId,
                'movement_type' => 'transfer_out',
                'quantity_delta' => bcsub('0', $quantity, 4),
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => $data['reason'] ?? 'Internal stock transfer dispatched',
                'reference_type' => 'inventory_transfer',
                'reference_id' => (string) $transfer->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'created_by' => $actor->id,
                'metadata' => ['transfer_uuid' => $transferUuid, 'ip_address' => $ipAddress],
            ]);

            $transfer = $transfer->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher']);
            $this->notifyTransferRecipients($transfer, 'dispatched');

            return $transfer;
        }, 3);

        return $transfer;
    }

    public function receive(InventoryTransfer $transfer, array $data, User $actor, ?string $ipAddress = null): InventoryTransfer
    {
        $this->assertLocationAccess($actor, (int) $transfer->destination_location_id, ['receive', 'manage'], 'transfer');
        if ((int) $transfer->dispatched_by === (int) $actor->id) {
            throw ValidationException::withMessages(['transfer' => 'The dispatcher cannot receive their own transfer.']);
        }

        $idempotencyKey = (string) ($data['idempotency_key'] ?? '');
        if (! Str::isUuid($idempotencyKey)) {
            throw ValidationException::withMessages(['idempotency_key' => 'A valid idempotency key is required for receipt.']);
        }
        $defaultRemaining = bcsub((string) $transfer->quantity_sent, (string) $transfer->quantity_received, 4);
        $receiveQty = $this->quantity($data['quantity_received'] ?? $defaultRemaining);
        $recipientNote = isset($data['recipient_note']) ? trim((string) $data['recipient_note']) : null;
        $payloadHash = hash('sha256', json_encode([
            'transfer_id' => (int) $transfer->id,
            'quantity_received' => bcadd($receiveQty, '0', 4),
            'recipient_note' => $recipientNote,
        ], JSON_THROW_ON_ERROR));
        $updated = DB::transaction(function () use ($transfer, $receiveQty, $recipientNote, $payloadHash, $idempotencyKey, $actor, $ipAddress): InventoryTransfer {
            // Serialize a key globally, including requests that race across transfer rows.
            DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', [$idempotencyKey]);
            $locked = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            $existingReceipt = InventoryTransferReceipt::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($existingReceipt) {
                if ((int) $existingReceipt->inventory_transfer_id !== (int) $locked->id || ! hash_equals($existingReceipt->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key was already used for a different receipt request.']);
                }

                return $locked->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher', 'receiver']);
            }

            if (! in_array($locked->status, ['in_transit', 'partially_received'], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only transfers awaiting receipt can be accepted.']);
            }

            $remaining = bcsub((string) $locked->quantity_sent, (string) $locked->quantity_received, 4);
            if (bccomp($receiveQty, $remaining, 4) > 0) {
                throw ValidationException::withMessages(['quantity_received' => 'Received quantity cannot exceed the outstanding transfer quantity.']);
            }

            $isVariant = $locked->product_variant_id !== null;
            // Lock the stock owner before looking for a stock row. This serializes
            // first-time stock-row creation across different transfers for the same owner.
            if ($isVariant) {
                $variantReference = DB::table('product_variants')->where('id', $locked->product_variant_id)->first(['product_id']);
                if (! $variantReference) {
                    throw ValidationException::withMessages(['transfer' => 'The transferred variant no longer exists.']);
                }
                DB::table('products')->where('id', $variantReference->product_id)->lockForUpdate()->first();
            } else {
                DB::table('products')->where('id', $locked->product_id)->lockForUpdate()->first();
            }

            $stockQuery = InventoryStock::query()->where('location_id', $locked->destination_location_id);
            $stockQuery = $isVariant
                ? $stockQuery->where('product_variant_id', $locked->product_variant_id)->whereNull('product_id')
                : $stockQuery->where('product_id', $locked->product_id)->whereNull('product_variant_id');
            $stock = $stockQuery->lockForUpdate()->first();
            if (! $stock) {
                $stock = InventoryStock::query()->create([
                    'product_id' => $locked->product_id,
                    'product_variant_id' => $locked->product_variant_id,
                    'location_id' => $locked->destination_location_id,
                    'quantity' => '0',
                    'reserved_quantity' => '0',
                    'low_stock_threshold' => '0',
                ]);
                $stock = InventoryStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            }

            $before = (string) $stock->quantity;
            $after = bcadd($before, $receiveQty, 4);
            $stock->quantity = $after;
            $stock->save();

            StockMovement::query()->create([
                'transfer_group_id' => $locked->transfer_uuid,
                'product_id' => $locked->product_id,
                'product_variant_id' => $locked->product_variant_id,
                'location_id' => $locked->destination_location_id,
                'movement_type' => 'transfer_in',
                'quantity_delta' => $receiveQty,
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => $recipientNote ?: 'Internal stock transfer received',
                'reference_type' => 'inventory_transfer',
                'reference_id' => (string) $locked->id,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor->id,
                'metadata' => ['transfer_uuid' => $locked->transfer_uuid, 'ip_address' => $ipAddress],
            ]);

            $newReceived = bcadd((string) $locked->quantity_received, $receiveQty, 4);
            $locked->quantity_received = $newReceived;
            $locked->status = bccomp($newReceived, (string) $locked->quantity_sent, 4) === 0 ? 'received' : 'partially_received';
            $locked->recipient_note = $recipientNote ?? $locked->recipient_note;
            $locked->received_by = $actor->id;
            $locked->received_at = $locked->status === 'received' ? now() : null;
            $locked->save();

            InventoryTransferReceipt::query()->create([
                'inventory_transfer_id' => $locked->id,
                'idempotency_key' => $idempotencyKey,
                'payload_hash' => $payloadHash,
                'quantity_received' => $receiveQty,
                'recipient_note' => $recipientNote,
                'received_by' => $actor->id,
                'resulting_quantity_received' => $newReceived,
                'resulting_status' => $locked->status,
            ]);
            $locked = $locked->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher', 'receiver']);
            $this->notifyDispatcher($locked, 'received');

            return $locked;
        }, 3);

        return $updated;
    }

    public function reject(InventoryTransfer $transfer, string $reason, User $actor): InventoryTransfer
    {
        $this->assertLocationAccess($actor, (int) $transfer->destination_location_id, ['receive', 'manage'], 'transfer');
        if ((int) $transfer->dispatched_by === (int) $actor->id) {
            throw ValidationException::withMessages(['transfer' => 'The dispatcher cannot reject their own transfer.']);
        }
        $updated = DB::transaction(function () use ($transfer, $reason, $actor): InventoryTransfer {
            $locked = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, ['in_transit', 'partially_received'], true)) {
                throw ValidationException::withMessages(['transfer' => 'Only an outstanding transfer can be rejected.']);
            }
            $locked->status = 'discrepancy';
            $locked->resolution_note = $reason;
            $locked->metadata = array_merge($locked->metadata ?? [], [
                'discrepancy' => [
                    'reason' => $reason,
                    'reported_by' => $actor->id,
                    'reported_at' => now()->toISOString(),
                ],
            ]);
            $locked->save();

            $locked = $locked->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher']);
            $this->notifyDispatcher($locked, 'discrepancy');

            return $locked;
        }, 3);

        return $updated;
    }

    private function findIdempotentTransfer(
        string $key,
        int $sourceId,
        int $destinationId,
        int $ownerId,
        bool $isVariant,
        string $quantity,
        array $data,
    ): ?InventoryTransfer {
        $existing = InventoryTransfer::query()->where('idempotency_key', $key)->first();
        if (! $existing) {
            return null;
        }

        $same = (int) $existing->source_location_id === $sourceId
            && (int) $existing->destination_location_id === $destinationId
            && (int) ($isVariant ? $existing->product_variant_id : $existing->product_id) === $ownerId
            && ($isVariant ? $existing->product_id === null : $existing->product_variant_id === null)
            && bccomp((string) $existing->quantity_sent, $quantity, 4) === 0
            && $existing->reason === ($data['reason'] ?? null)
            && $existing->reference_type === ($data['reference_type'] ?? null)
            && $existing->reference_id === (isset($data['reference_id']) ? (string) $data['reference_id'] : null)
            && ($existing->metadata ?? null) == ($data['metadata'] ?? null);

        if (! $same) {
            throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different transfer.']);
        }

        return $existing;
    }

    private function notifyTransferRecipients(InventoryTransfer $transfer, string $event): void
    {
        $recipients = User::query()->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
            ->whereHas('locations', fn ($query) => $query
                ->where('locations.id', $transfer->destination_location_id)
                ->whereIn('location_user.access_level', ['receive', 'manage']))
            ->where('id', '<>', $transfer->dispatched_by)
            ->get();

        Notification::send($recipients, new InventoryTransferNotification($transfer, $event));
    }

    private function assertLocationAccess(User $actor, int $locationId, array $allowedLevels, string $field): void
    {
        if (! $this->authorization->can($actor, 'system.inventory.transfer')) {
            abort(403, 'You do not have permission to manage inventory transfers.');
        }

        if ($this->authorization->can($actor, 'system.locations.manage')) {
            return;
        }

        $allowed = $actor->locations()
            ->where('locations.id', $locationId)
            ->whereIn('location_user.access_level', $allowedLevels)
            ->exists();

        if (! $allowed) {
            throw ValidationException::withMessages([$field => 'You are not assigned the required access level for this location.']);
        }
    }

    private function notifyDispatcher(InventoryTransfer $transfer, string $event): void
    {
        $dispatcher = User::query()->find($transfer->dispatched_by);
        if ($dispatcher) {
            $dispatcher->notify(new InventoryTransferNotification($transfer, $event));
        }
    }

    private function quantity(mixed $value): string
    {
        if (! is_numeric($value) || ! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', (string) $value) || bccomp((string) $value, '0', 4) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be positive with up to four decimal places.']);
        }

        return bcadd((string) $value, '0', 4);
    }
}
