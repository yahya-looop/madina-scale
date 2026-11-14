<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    /**
     * Receive stock into a location.
     *
     * Required:
     * - location_id
     * - exactly one of product_id or product_variant_id
     * - quantity: positive decimal with up to 4 decimal places
     *
     * @param  array<string, mixed>  $data
     */
    public function receive(
        array $data,
        ?User $actor = null,
    ): StockMovement {
        return $this->move(
            data: $data,
            type: 'receipt',
            direction: 1,
            actor: $actor,
        );
    }

    /**
     * Issue stock from a location.
     *
     * @param  array<string, mixed>  $data
     */
    public function issue(
        array $data,
        ?User $actor = null,
    ): StockMovement {
        return $this->move(
            data: $data,
            type: 'issue',
            direction: -1,
            actor: $actor,
        );
    }

    /**
     * Apply a signed stock adjustment.
     *
     * Positive quantity increases stock.
     * Negative quantity decreases stock.
     *
     * @param  array<string, mixed>  $data
     */
    public function adjust(
        array $data,
        ?User $actor = null,
    ): StockMovement {
        $quantity = $this->normalizeQuantity(
            $data['quantity'] ?? null,
        );

        if (bccomp($quantity, '0', 4) === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Adjustment quantity cannot be zero.',
            ]);
        }

        $data['quantity'] = $quantity;

        return $this->move(
            data: $data,
            type: 'adjustment',
            direction: null,
            actor: $actor,
        );
    }

    /**
     * Transfer stock between two different locations.
     *
     * The source and destination movements are committed atomically.
     *
     * @param  array<string, mixed>  $data
     * @return array{transfer_group_id: string, outgoing: StockMovement, incoming: StockMovement}
     */
    public function transfer(array $data, ?User $actor = null): array
    {
        $quantity = $this->normalizeQuantity($data['quantity'] ?? null);

        if (bccomp($quantity, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Transfer quantity must be greater than zero.',
            ]);
        }

        $sourceId = filter_var($data['source_location_id'] ?? null, FILTER_VALIDATE_INT);
        $destinationId = filter_var($data['destination_location_id'] ?? null, FILTER_VALIDATE_INT);

        if ($sourceId === false || $destinationId === false || $sourceId < 1 || $destinationId < 1) {
            throw ValidationException::withMessages([
                'location_id' => 'Valid source and destination locations are required.',
            ]);
        }

        if ($sourceId === $destinationId) {
            throw ValidationException::withMessages([
                'destination_location_id' => 'Source and destination must be different.',
            ]);
        }

        $idempotencyKey = $data['idempotency_key'] ?? null;

        if ($idempotencyKey !== null
            && (! is_string($idempotencyKey) || ! Str::isUuid($idempotencyKey))) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key must be a valid UUID.',
            ]);
        }

        return DB::transaction(function () use (
            $data,
            $quantity,
            $sourceId,
            $destinationId,
            $idempotencyKey,
            $actor
        ): array {
            // Serialize requests using the same key, including requests
            // that target different products.
            if ($idempotencyKey !== null) {
                DB::selectOne(
                    'SELECT pg_advisory_xact_lock(hashtext(?))',
                    [$idempotencyKey]
                );
            }

            $owner = $this->lockOwner($data);

            // A transfer stores its idempotency key on the outgoing entry.
            if ($idempotencyKey !== null) {
                $existing = StockMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    $incoming = $existing->transfer_group_id !== null
                        ? StockMovement::query()
                            ->where('transfer_group_id', $existing->transfer_group_id)
                            ->where('movement_type', 'transfer_in')
                            ->first()
                        : null;

                    $sameRequest = $existing->movement_type === 'transfer_out'
                        && $incoming !== null
                        && (int) $existing->location_id === $sourceId
                        && (int) $incoming->location_id === $destinationId
                        && $existing->product_id === $owner['product_id']
                        && $existing->product_variant_id === $owner['variant_id']
                        && $incoming->product_id === $owner['product_id']
                        && $incoming->product_variant_id === $owner['variant_id']
                        && bccomp(
                            $existing->quantity_delta,
                            bcsub('0', $quantity, 4),
                            4
                        ) === 0
                        && bccomp(
                            $incoming->quantity_delta,
                            $quantity,
                            4
                        ) === 0
                        && $existing->reason === ($data['reason'] ?? null)
                        && $existing->reference_type === ($data['reference_type'] ?? null)
                        && $existing->reference_id === (
                            isset($data['reference_id'])
                                ? (string) $data['reference_id']
                                : null
                        )
                        && $existing->metadata === ($data['metadata'] ?? null);

                    if (! $sameRequest) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This key has already been used for a different stock movement.',
                        ]);
                    }

                    return [
                        'transfer_group_id' => $existing->transfer_group_id,
                        'outgoing' => $existing,
                        'incoming' => $incoming,
                    ];
                }
            }

            $locations = Location::query()
                ->whereIn('id', [$sourceId, $destinationId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($locations->count() !== 2) {
                throw ValidationException::withMessages([
                    'location_id' => 'One or both locations do not exist.',
                ]);
            }

            foreach ($locations as $location) {
                if (! $location->is_active) {
                    throw ValidationException::withMessages([
                        'location_id' => 'Stock cannot be transferred through an inactive location.',
                    ]);
                }
            }

            $source = $this->getOrCreateStock($owner, $sourceId);
            $destination = $this->getOrCreateStock($owner, $destinationId);

            $sourceBefore = $source->quantity;
            $sourceAfter = bcsub($sourceBefore, $quantity, 4);

            if (bccomp($sourceAfter, $source->reserved_quantity, 4) < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient available stock at the source location.',
                ]);
            }

            $destinationBefore = $destination->quantity;
            $destinationAfter = bcadd($destinationBefore, $quantity, 4);

            $source->quantity = $sourceAfter;
            $destination->quantity = $destinationAfter;
            $source->save();
            $destination->save();

            $transferGroupId = (string) Str::uuid();

            $common = [
                'product_id' => $owner['product_id'],
                'product_variant_id' => $owner['variant_id'],
                'transfer_group_id' => $transferGroupId,
                'reason' => $data['reason'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => isset($data['reference_id'])
                    ? (string) $data['reference_id']
                    : null,
                'created_by' => $actor?->id,
                'metadata' => $data['metadata'] ?? null,
            ];

            $outgoing = StockMovement::query()->create([
                ...$common,
                'location_id' => $sourceId,
                'movement_type' => 'transfer_out',
                'quantity_delta' => bcsub('0', $quantity, 4),
                'balance_before' => $sourceBefore,
                'balance_after' => $sourceAfter,
                'idempotency_key' => $idempotencyKey,
            ]);

            $incoming = StockMovement::query()->create([
                ...$common,
                'location_id' => $destinationId,
                'movement_type' => 'transfer_in',
                'quantity_delta' => $quantity,
                'balance_before' => $destinationBefore,
                'balance_after' => $destinationAfter,
            ]);

            $valuation = app(InventoryValuationService::class);
            $carryingCost = (string) ($source->average_unit_cost ?? '0');
            $valuation->apply($source, $outgoing, $carryingCost, $actor, null, ['transfer_group_id' => $transferGroupId]);
            $valuation->apply($destination, $incoming, $carryingCost, $actor, null, ['transfer_group_id' => $transferGroupId]);

            return [
                'transfer_group_id' => $transferGroupId,
                'outgoing' => $outgoing,
                'incoming' => $incoming,
            ];
        }, 3);
    }

    /**
     * Perform one stock movement.
     *
     * Locking the parent product serializes stock-row creation for that
     * product, including when a location has no stock row yet.
     *
     * @param  array<string, mixed>  $data
     */
    private function move(
        array $data,
        string $type,
        ?int $direction,
        ?User $actor,
    ): StockMovement {
        $quantity = $this->normalizeQuantity(
            $data['quantity'] ?? null,
        );

        if (
            $direction !== null
            && bccomp($quantity, '0', 4) <= 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be greater than zero.',
            ]);
        }

        if (
            $type === 'adjustment'
            && bccomp($quantity, '0', 4) === 0
        ) {
            throw ValidationException::withMessages([
                'quantity' => 'Adjustment quantity cannot be zero.',
            ]);
        }

        $signedDelta = match ($type) {
            'receipt' => $quantity,
            'issue' => bcsub('0', $quantity, 4),
            'adjustment' => $quantity,
            default => throw new \InvalidArgumentException(
                'Unsupported movement type.',
            ),
        };

        $idempotencyKey = $data['idempotency_key'] ?? null;

        if (
            $idempotencyKey !== null
            && (
                ! is_string($idempotencyKey)
                || ! Str::isUuid($idempotencyKey)
            )
        ) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'The idempotency key must be a valid UUID.',
            ]);
        }

        return DB::transaction(function () use (
            $data,
            $type,
            $signedDelta,
            $idempotencyKey,
            $actor,
        ): StockMovement {
            if ($idempotencyKey !== null) {
                DB::selectOne(
                    'SELECT pg_advisory_xact_lock(hashtext(?))',
                    [$idempotencyKey],
                );
            }

            if ($idempotencyKey !== null) {
                $existing = StockMovement::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();

                if ($existing !== null) {
                    $sameRequest =
                        $existing->movement_type === $type
                        && (int) $existing->location_id
                            === (int) ($data['location_id'] ?? 0)
                        && $existing->product_id
                            === $this->nullableInt($data['product_id'] ?? null)
                        && $existing->product_variant_id
                            === $this->nullableInt($data['product_variant_id'] ?? null)
                        && $existing->reason === ($data['reason'] ?? null)

                        && $existing->reference_type === ($data['reference_type'] ?? null)

                        && $existing->reference_id === (
                            isset($data['reference_id'])
                                ? (string) $data['reference_id']
                                : null
                        )
                        && $existing->metadata === ($data['metadata'] ?? null)

                        && bccomp(
                            $existing->quantity_delta,
                            $signedDelta,
                            4,
                        ) === 0;

                    if (! $sameRequest) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'This key has already been used for a different stock movement.',
                        ]);
                    }

                    return $existing;
                }
            }

            $owner = $this->lockOwner($data);

            $locationId = filter_var(
                $data['location_id'] ?? null,
                FILTER_VALIDATE_INT,
            );

            if ($locationId === false || $locationId < 1) {
                throw ValidationException::withMessages([
                    'location_id' => 'A valid location is required.',
                ]);
            }

            $location = Location::query()
                ->lockForUpdate()
                ->find($locationId);

            if ($location === null || ! $location->is_active) {
                throw ValidationException::withMessages([
                    'location_id' => 'An existing active location is required.',
                ]);
            }

            $stock = $this->getOrCreateStock(
                $owner,
                $locationId,
            );

            $before = $stock->quantity;
            $after = bcadd($before, $signedDelta, 4);

            if (
                bccomp(
                    $after,
                    $stock->reserved_quantity,
                    4,
                ) < 0
            ) {
                throw ValidationException::withMessages([
                    'quantity' => 'Insufficient available stock for this movement.',
                ]);
            }

            $stock->quantity = $after;
            $stock->save();

            $movement = StockMovement::query()->create([
                'product_id' => $owner['product_id'],
                'product_variant_id' => $owner['variant_id'],
                'location_id' => $locationId,
                'movement_type' => $type,
                'quantity_delta' => $signedDelta,
                'balance_before' => $before,
                'balance_after' => $after,
                'reason' => $data['reason'] ?? null,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => isset($data['reference_id'])
                    ? (string) $data['reference_id']
                    : null,
                'idempotency_key' => $idempotencyKey,
                'created_by' => $actor?->id,
                'ip_address' => $data['ip_address'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]);

            $unitCost = isset($data['unit_cost']) ? (string) $data['unit_cost'] : '0';
            app(InventoryValuationService::class)->apply(
                $stock,
                $movement,
                $unitCost,
                $actor,
                $idempotencyKey,
                ['reason' => $data['reason'] ?? null],
            );

            return $movement;
        }, 3);
    }

    /**
     * Lock and validate the stock owner.
     *
     * Simple products own stock directly.
     * Variable products own stock through their variants.
     *
     * @param  array<string, mixed>  $data
     * @return array{product_id: ?int, variant_id: ?int}
     */
    private function lockOwner(array $data): array
    {
        $productId = $this->nullableInt(
            $data['product_id'] ?? null,
        );

        $variantId = $this->nullableInt(
            $data['product_variant_id'] ?? null,
        );

        if (($productId === null) === ($variantId === null)) {
            throw ValidationException::withMessages([
                'product_id' => 'Provide exactly one of product_id or product_variant_id.',
            ]);
        }

        if ($productId !== null) {
            $product = Product::query()
                ->lockForUpdate()
                ->find($productId);

            if ($product === null) {
                throw ValidationException::withMessages([
                    'product_id' => 'The selected product does not exist.',
                ]);
            }

            if (! $product->isSimple()) {
                throw ValidationException::withMessages([
                    'product_id' => 'Only simple products can own stock directly. Use a variant for variable products.',
                ]);
            }

            return [
                'product_id' => $product->id,
                'variant_id' => null,
            ];
        }

        // Read the parent ID first, then lock parent before variant.
        $variantReference = ProductVariant::query()
            ->whereKey($variantId)
            ->first(['id', 'product_id']);

        if ($variantReference === null) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected variant does not exist.',
            ]);
        }

        $product = Product::query()
            ->lockForUpdate()
            ->find($variantReference->product_id);

        if ($product === null || ! $product->isVariable()) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The variant must belong to a variable product.',
            ]);
        }

        $variant = ProductVariant::query()
            ->whereKey($variantId)
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->first();

        if ($variant === null || $variant->trashed()) {
            throw ValidationException::withMessages([
                'product_variant_id' => 'The selected variant is unavailable.',
            ]);
        }

        return [
            'product_id' => null,
            'variant_id' => $variant->id,
        ];
    }

    /**
     * Retrieve the stock row or create a zero-balance row.
     *
     * The caller must already hold locks on the owner and location.
     *
     * @param  array{product_id: ?int, variant_id: ?int}  $owner
     */
    private function getOrCreateStock(
        array $owner,
        int $locationId,
    ): InventoryStock {
        $query = InventoryStock::query()
            ->where('location_id', $locationId);

        if ($owner['product_id'] !== null) {
            $query->where('product_id', $owner['product_id'])
                ->whereNull('product_variant_id');
        } else {
            $query->whereNull('product_id')
                ->where(
                    'product_variant_id',
                    $owner['variant_id'],
                );
        }

        $stock = $query->lockForUpdate()->first();

        if ($stock !== null) {
            return $stock;
        }

        return InventoryStock::query()->create([
            'product_id' => $owner['product_id'],
            'product_variant_id' => $owner['variant_id'],
            'location_id' => $locationId,
            'quantity' => '0.0000',
            'reserved_quantity' => '0.0000',
        ]);
    }

    /**
     * Normalize decimal input to exactly four fractional places.
     *
     * @return numeric-string
     */
    private function normalizeQuantity(mixed $value): string
    {
        if (
            (! is_string($value) && ! is_int($value) && ! is_float($value))
            || ! is_finite((float) $value)
            || ! preg_match(
                '/^-?\d{1,14}(?:\.\d{1,4})?$/',
                (string) $value,
            )
        ) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a valid decimal with up to 4 decimal places.',
            ]);
        }

        $normalized = (string) $value;
        $this->assertNumericQuantity($normalized);

        return bcadd($normalized, '0', 4);
    }

    /**
     * @phpstan-assert numeric-string $value
     */
    private function assertNumericQuantity(string $value): void
    {
        if (! preg_match('/^-?\\d{1,14}(?:\\.\\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([
                'quantity' => 'Quantity must be a valid decimal with up to 4 decimal places.',
            ]);
        }
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $result = filter_var($value, FILTER_VALIDATE_INT);

        return $result === false ? null : $result;
    }
}
