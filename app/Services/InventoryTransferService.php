<?php

namespace App\Services;

use App\Jobs\ProcessInventoryTransferNotificationOutbox;
use App\Models\InventoryStock;
use App\Models\InventoryTransfer;
use App\Models\InventoryTransferNotificationOutbox;
use App\Models\InventoryTransferReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryTransferService
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    /**
     * @param  array<string, mixed>  $data
     */
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

            // Keep a single lock order across inventory workflows: owner, locations, stock row.
            // The parent product lock serializes first-time stock-row creation for variants too.
            $owner = $this->lockOwner($ownerId, $isVariant);

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

            $stockQuery = InventoryStock::query()->where('location_id', $sourceId);
            $stockQuery = $isVariant
                ? $stockQuery->where('product_variant_id', $ownerId)->whereNull('product_id')
                : $stockQuery->where('product_id', $ownerId)->whereNull('product_variant_id');
            $stock = $stockQuery->lockForUpdate()->first();
            if (! $stock) {
                throw ValidationException::withMessages(['quantity' => 'No stock record exists at the source location.']);
            }

            $after = bcsub($stock->quantity, $quantity, 4);
            if (bccomp($after, $stock->reserved_quantity, 4) < 0) {
                throw ValidationException::withMessages(['quantity' => 'Insufficient available stock. Reserved stock cannot be dispatched.']);
            }

            $transferUuid = (string) Str::uuid();
            $transfer = InventoryTransfer::query()->create([
                'transfer_uuid' => $transferUuid,
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'product_id' => $owner['product_id'],
                'product_variant_id' => $owner['variant_id'],
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

            $before = $stock->quantity;
            $stock->quantity = $after;
            $stock->save();

            StockMovement::query()->create([
                'transfer_group_id' => $transferUuid,
                'product_id' => $owner['product_id'],
                'product_variant_id' => $owner['variant_id'],
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
            $this->recordNotificationOutbox($transfer, 'dispatched', $this->destinationRecipientIds($transfer));

            return $transfer;
        }, 3);

        return $transfer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
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
        $defaultRemaining = bcsub($transfer->quantity_sent, $transfer->quantity_received, 4);
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

            $remaining = bcsub($locked->quantity_sent, $locked->quantity_received, 4);
            if (bccomp($receiveQty, $remaining, 4) > 0) {
                throw ValidationException::withMessages(['quantity_received' => 'Received quantity cannot exceed the outstanding transfer quantity.']);
            }

            $isVariant = $locked->product_variant_id !== null;
            // Revalidate and lock the owner (parent product before variant) before stock.
            $this->lockOwner(
                (int) ($isVariant ? $locked->product_variant_id : $locked->product_id),
                $isVariant,
            );

            Location::query()->lockForUpdate()->findOrFail($locked->destination_location_id);

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

            $before = $stock->quantity;
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

            $newReceived = bcadd($locked->quantity_received, $receiveQty, 4);
            $isFullyReceived = bccomp($newReceived, $locked->quantity_sent, 4) === 0;
            $locked->quantity_received = $newReceived;
            $locked->status = $isFullyReceived ? 'received' : 'partially_received';
            $locked->recipient_note = $recipientNote ?? $locked->recipient_note;
            $locked->setAttribute('received_by', $actor->getKey());
            $locked->received_at = $isFullyReceived ? Carbon::now() : null;
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
            $this->recordNotificationOutbox($locked, 'received', $this->dispatcherRecipientIds($locked));

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
            $locked->metadata = array_merge((array) ($locked->metadata ?? []), [
                'discrepancy' => [
                    'reason' => $reason,
                    'reported_by' => $actor->id,
                    'reported_at' => now()->toISOString(),
                ],
            ]);
            $locked->save();

            $locked = $locked->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher']);
            $this->recordNotificationOutbox($locked, 'discrepancy', $this->dispatcherRecipientIds($locked));

            return $locked;
        }, 3);

        return $updated;
    }

    /**
     * Resolve an audited transfer discrepancy without silently changing either balance.
     *
     * @param  'receive_remaining'|'return_to_source'|'write_off'  $action
     */
    public function resolveDiscrepancy(InventoryTransfer $transfer, string $action, string $note, User $actor): InventoryTransfer
    {
        $this->assertLocationAccess($actor, (int) $transfer->source_location_id, ['manage'], 'transfer');

        return DB::transaction(function () use ($transfer, $action, $note, $actor): InventoryTransfer {
            $locked = InventoryTransfer::query()->whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'discrepancy') {
                $previousResolution = (array) (($locked->metadata ?? [])['discrepancy_resolution'] ?? []);
                if (($previousResolution['action'] ?? null) === $action
                    && ($previousResolution['note'] ?? null) === $note) {
                    return $locked->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher', 'receiver', 'resolver']);
                }

                throw ValidationException::withMessages(['transfer' => 'Only an unresolved discrepancy can be reconciled; this transfer may already have a different resolution.']);
            }

            $outstanding = bcsub((string) $locked->quantity_sent, (string) $locked->quantity_received, 4);
            if (bccomp($outstanding, '0.0000', 4) <= 0) {
                throw ValidationException::withMessages(['transfer' => 'This transfer has no outstanding quantity to reconcile.']);
            }

            $isVariant = $locked->product_variant_id !== null;
            $ownerId = (int) ($isVariant ? $locked->product_variant_id : $locked->product_id);
            $owner = $this->lockOwner($ownerId, $isVariant);

            if ($action === 'receive_remaining' || $action === 'return_to_source') {
                $locationId = $action === 'receive_remaining'
                    ? (int) $locked->destination_location_id
                    : (int) $locked->source_location_id;

                Location::query()->lockForUpdate()->findOrFail($locationId);
                $stockQuery = InventoryStock::query()->where('location_id', $locationId);
                $stockQuery = $isVariant
                    ? $stockQuery->where('product_variant_id', $owner['variant_id'])->whereNull('product_id')
                    : $stockQuery->where('product_id', $owner['product_id'])->whereNull('product_variant_id');
                $stock = $stockQuery->lockForUpdate()->first();

                if (! $stock) {
                    $stock = InventoryStock::query()->create([
                        'product_id' => $owner['product_id'],
                        'product_variant_id' => $owner['variant_id'],
                        'location_id' => $locationId,
                        'quantity' => '0.0000',
                        'reserved_quantity' => '0.0000',
                    ]);
                }

                $before = (string) $stock->quantity;
                $after = bcadd($before, $outstanding, 4);
                $stock->quantity = $after;
                $stock->save();

                StockMovement::query()->create([
                    'transfer_group_id' => $locked->transfer_uuid,
                    'product_id' => $owner['product_id'],
                    'product_variant_id' => $owner['variant_id'],
                    'location_id' => $locationId,
                    'movement_type' => $action === 'receive_remaining' ? 'transfer_in' : 'reversal',
                    'quantity_delta' => $outstanding,
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reason' => 'Transfer discrepancy resolution: '.$note,
                    'reference_type' => 'inventory_transfer',
                    'reference_id' => (string) $locked->id,
                    'idempotency_key' => (string) Str::uuid(),
                    'created_by' => $actor->id,
                    'metadata' => [
                        'transfer_uuid' => $locked->transfer_uuid,
                        'resolution_action' => $action,
                        'resolution_note' => $note,
                    ],
                ]);
            }

            if ($action === 'receive_remaining') {
                $locked->quantity_received = $locked->quantity_sent;
                $locked->status = 'received';
                $locked->received_at = Carbon::now();
                $locked->received_by = $actor->id;
            } elseif ($action === 'return_to_source') {
                $locked->status = 'rejected';
            } else {
                $locked->status = 'written_off';
            }

            $locked->resolution_note = $note;
            $locked->resolved_by = $actor->id;
            $locked->resolved_at = Carbon::now();
            $locked->metadata = array_merge((array) ($locked->metadata ?? []), [
                'discrepancy_resolution' => [
                    'action' => $action,
                    'quantity' => $outstanding,
                    'note' => $note,
                    'resolved_by' => $actor->id,
                    'resolved_at' => now()->toISOString(),
                ],
            ]);
            $locked->save();

            $recipientIds = array_values(array_unique([
                ...$this->dispatcherRecipientIds($locked),
                ...$this->destinationRecipientIds($locked),
            ]));
            $this->recordNotificationOutbox($locked, 'discrepancy_resolved', $recipientIds);

            return $locked->load(['product', 'variant', 'sourceLocation', 'destinationLocation', 'dispatcher', 'receiver', 'resolver']);
        }, 3);
    }

    /**
     * @param  numeric-string  $quantity
     * @param  array<string, mixed>  $data
     */
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
            && bccomp($existing->quantity_sent, $quantity, 4) === 0
            && $existing->reason === ($data['reason'] ?? null)
            && $existing->reference_type === ($data['reference_type'] ?? null)
            && $existing->reference_id === (isset($data['reference_id']) ? (string) $data['reference_id'] : null)
            && ($existing->metadata ?? null) == ($data['metadata'] ?? null);

        if (! $same) {
            throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different transfer.']);
        }

        return $existing;
    }

    /**
     * Persist notification intent in the same transaction as the inventory change.
     * The outbox worker delivers after commit and retries without rolling back stock.
     *
     * @param  list<int>  $recipientIds
     */
    private function recordNotificationOutbox(InventoryTransfer $transfer, string $event, array $recipientIds): void
    {
        if ($recipientIds === []) {
            return;
        }

        $outbox = InventoryTransferNotificationOutbox::query()->create([
            'inventory_transfer_id' => $transfer->id,
            'event' => $event,
            'recipient_ids' => array_values(array_unique($recipientIds)),
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => now(),
        ]);

        DB::afterCommit(function () use ($outbox): void {
            try {
                ProcessInventoryTransferNotificationOutbox::dispatch((int) $outbox->id);
            } catch (\Throwable $exception) {
                // The durable pending row is picked up by inventory:dispatch-transfer-notifications.
                report($exception);
            }
        });
    }

    /** @return list<int> */
    private function destinationRecipientIds(InventoryTransfer $transfer): array
    {
        return array_values(User::query()->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
            ->whereHas('locations', fn ($query) => $query
                ->where('locations.id', $transfer->destination_location_id)
                ->whereIn('location_user.access_level', ['receive', 'manage']))
            ->where('id', '<>', $transfer->dispatched_by)
            ->pluck('id')->map(fn ($id) => (int) $id)->all());
    }

    /** @return list<int> */
    private function dispatcherRecipientIds(InventoryTransfer $transfer): array
    {
        return $transfer->dispatched_by ? [(int) $transfer->dispatched_by] : [];
    }

    /**
     * @return array{product_id: int|null, variant_id: int|null}
     */
    private function lockOwner(int $ownerId, bool $isVariant): array
    {
        if (! $isVariant) {
            $product = Product::query()->lockForUpdate()->find($ownerId);
            if (! $product || ! $product->isSimple()) {
                throw ValidationException::withMessages(['product_id' => 'Choose an existing simple product.']);
            }

            return ['product_id' => (int) $product->id, 'variant_id' => null];
        }

        $reference = ProductVariant::query()->whereKey($ownerId)->first(['id', 'product_id']);
        if (! $reference) {
            throw ValidationException::withMessages(['product_variant_id' => 'The selected variant does not exist.']);
        }

        $product = Product::query()->lockForUpdate()->find($reference->product_id);
        $variant = ProductVariant::query()
            ->whereKey($ownerId)
            ->where('product_id', $product?->id)
            ->lockForUpdate()
            ->first();

        if (! $product || ! $product->isVariable() || ! $variant || $variant->trashed()) {
            throw ValidationException::withMessages(['product_variant_id' => 'Choose an available variant.']);
        }

        return ['product_id' => null, 'variant_id' => (int) $variant->id];
    }

    /**
     * @param  list<string>  $allowedLevels
     */
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

    /**
     * @return numeric-string
     */
    private function quantity(mixed $value): string
    {
        if (! is_numeric($value) || ! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', (string) $value) || bccomp((string) $value, '0', 4) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be positive with up to four decimal places.']);
        }

        $normalized = (string) $value;

        return bcadd($normalized, '0', 4);
    }
}
