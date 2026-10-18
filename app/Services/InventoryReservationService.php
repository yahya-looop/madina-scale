<?php

namespace App\Services;

use App\Models\InventoryReservation;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InventoryReservationService
{
    public function reserve(array $data, User $actor): InventoryReservation
    {
        $this->expireDue();
        return DB::transaction(function () use ($data, $actor): InventoryReservation {
            $owner = $this->lockOwner($data);
            $location = Location::query()->lockForUpdate()->find($data['location_id']);
            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'Choose an existing active location.']);
            }
            $stock = $this->stockForUpdate($owner, (int) $location->id);
            $quantity = $this->quantity($data['quantity'] ?? null);
            $available = bcsub((string) $stock->quantity, (string) $stock->reserved_quantity, 4);
            if (bccomp($available, $quantity, 4) < 0) {
                throw ValidationException::withMessages(['quantity' => 'Reservation exceeds available stock.']);
            }
            $stock->reserved_quantity = bcadd((string) $stock->reserved_quantity, $quantity, 4);
            $stock->save();

            return InventoryReservation::query()->create([
                'product_id' => $owner['product_id'],
                'product_variant_id' => $owner['variant_id'],
                'location_id' => $location->id,
                'quantity' => $quantity,
                'purpose' => $data['purpose'],
                'status' => 'active',
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => isset($data['reference_id']) ? (string) $data['reference_id'] : null,
                'reason' => $data['reason'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'created_by' => $actor->id,
            ]);
        }, 3);
    }

    public function resolve(InventoryReservation $reservation, string $action, User $actor): InventoryReservation
    {
        $this->expireDue();
        return DB::transaction(function () use ($reservation, $action, $actor): InventoryReservation {
            $locked = InventoryReservation::query()->lockForUpdate()->findOrFail($reservation->id);
            if ($locked->status === 'expired') {
                return $locked->refresh();
            }
            if ($locked->status !== 'active') {
                throw ValidationException::withMessages(['reservation' => 'Only active reservations can be changed.']);
            }
            $owner = ['product_id' => $locked->product_id, 'variant_id' => $locked->product_variant_id];
            $location = Location::query()->lockForUpdate()->findOrFail($locked->location_id);
            $stock = $this->stockForUpdate($owner, (int) $location->id);
            $quantity = (string) $locked->quantity;
            if (bccomp((string) $stock->reserved_quantity, $quantity, 4) < 0) {
                throw ValidationException::withMessages(['reservation' => 'Reserved balance is inconsistent; review stock ledger before proceeding.']);
            }

            // A large expiry backlog can leave this active row outside expireDue()'s
            // bounded batch. Enforce expiry again while holding this reservation's lock.
            if ($locked->expires_at && ! $locked->expires_at->isFuture()) {
                $stock->reserved_quantity = bcsub((string) $stock->reserved_quantity, $quantity, 4);
                $stock->save();
                $locked->status = 'expired';
                $locked->resolved_by = $actor->id;
                $locked->resolved_at = now();
                $locked->save();
                return $locked->refresh();
            }

            $stock->reserved_quantity = bcsub((string) $stock->reserved_quantity, $quantity, 4);
            if ($action === 'fulfill') {
                if (bccomp((string) $stock->quantity, $quantity, 4) < 0) {
                    throw ValidationException::withMessages(['reservation' => 'On-hand stock is insufficient to fulfill this reservation.']);
                }
                $before = (string) $stock->quantity;
                $after = bcsub($before, $quantity, 4);
                $stock->quantity = $after;
                $stock->save();
                StockMovement::query()->create([
                    'product_id' => $owner['product_id'],
                    'product_variant_id' => $owner['variant_id'],
                    'location_id' => $location->id,
                    'movement_type' => $locked->purpose === 'customer_order' ? 'order_fulfillment' : 'issue',
                    'quantity_delta' => bcsub('0', $quantity, 4),
                    'balance_before' => $before,
                    'balance_after' => $after,
                    'reason' => ($locked->purpose === 'customer_order' ? 'Order Out (Fulfillment)' : 'Reservation fulfilled') . ': ' . $locked->id,
                    'reference_type' => 'inventory_reservation',
                    'reference_id' => (string) $locked->id,
                    'idempotency_key' => (string) Str::uuid(),
                    'created_by' => $actor->id,
                    'metadata' => ['reservation_id' => $locked->id, 'purpose' => $locked->purpose],
                ]);
                $locked->status = 'fulfilled';
            } else {
                $stock->save();
                $locked->status = $action === 'cancel' ? 'cancelled' : 'released';
            }
            $locked->resolved_by = $actor->id;
            $locked->resolved_at = now();
            $locked->save();
            return $locked->refresh();
        }, 3);
    }

    public function expireDue(): void
    {
        $ids = InventoryReservation::query()->where('status', 'active')->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('id')->limit(200)->pluck('id');
        foreach ($ids as $id) {
            try {
                DB::transaction(function () use ($id): void {
                    $r = InventoryReservation::query()->lockForUpdate()->find($id);
                    if (! $r || $r->status !== 'active' || ! $r->expires_at || $r->expires_at->isFuture()) return;
                    $owner = ['product_id' => $r->product_id, 'variant_id' => $r->product_variant_id];
                    $stock = $this->stockForUpdate($owner, (int) $r->location_id);
                    $stock->reserved_quantity = bcsub((string) $stock->reserved_quantity, (string) $r->quantity, 4);
                    if (bccomp((string) $stock->reserved_quantity, '0', 4) < 0) {
                        throw ValidationException::withMessages(['reservation' => 'Reserved balance is inconsistent; review stock ledger.']);
                    }
                    $stock->save();
                    $r->status = 'expired'; $r->resolved_at = now(); $r->save();
                }, 3);
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function lockOwner(array $data): array
    {
        $productId = isset($data['product_id']) ? (int) $data['product_id'] : null;
        $variantId = isset($data['product_variant_id']) ? (int) $data['product_variant_id'] : null;
        if (($productId === null) === ($variantId === null)) throw ValidationException::withMessages(['product_id' => 'Provide exactly one product or variant.']);
        if ($productId !== null) {
            $product = Product::query()->lockForUpdate()->find($productId);
            if (! $product || ! $product->isSimple()) throw ValidationException::withMessages(['product_id' => 'Choose an existing simple product.']);
            return ['product_id' => $product->id, 'variant_id' => null];
        }
        $ref = ProductVariant::query()->whereKey($variantId)->first(['id', 'product_id']);
        if (! $ref) throw ValidationException::withMessages(['product_variant_id' => 'Variant not found.']);
        $product = Product::query()->lockForUpdate()->find($ref->product_id);
        $variant = ProductVariant::query()->whereKey($variantId)->where('product_id', $product?->id)->lockForUpdate()->first();
        if (! $product || ! $product->isVariable() || ! $variant || $variant->trashed()) throw ValidationException::withMessages(['product_variant_id' => 'Choose an available variant.']);
        return ['product_id' => null, 'variant_id' => $variant->id];
    }

    private function stockForUpdate(array $owner, int $locationId): InventoryStock
    {
        $query = InventoryStock::query()->where('location_id', $locationId);
        $owner['product_id'] !== null ? $query->where('product_id', $owner['product_id'])->whereNull('product_variant_id') : $query->whereNull('product_id')->where('product_variant_id', $owner['variant_id']);
        $stock = $query->lockForUpdate()->first();
        if ($stock) return $stock;
        return InventoryStock::query()->create(['product_id' => $owner['product_id'], 'product_variant_id' => $owner['variant_id'], 'location_id' => $locationId, 'quantity' => '0.0000', 'reserved_quantity' => '0.0000']);
    }

    private function quantity(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || ! is_finite((float) $value) || ! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', (string) $value) || bccomp((string) $value, '0', 4) <= 0) {
            throw ValidationException::withMessages(['quantity' => 'Quantity must be positive with up to 4 decimal places.']);
        }
        return bcadd((string) $value, '0', 4);
    }
}
