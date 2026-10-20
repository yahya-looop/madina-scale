<?php

namespace App\Services;

use App\Models\InventoryReservation;
use App\Models\InventoryReservationPurpose;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderFulfillment;
use App\Models\SalesOrderFulfillmentItem;
use App\Models\SalesOrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(private readonly InventoryReservationService $reservations, private readonly OrderRiskService $risk) {}

    public function create(array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($data, $actor): SalesOrder {
            if (!empty($data['idempotency_key'])) {
                $existing = SalesOrder::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) return $existing->load(['items', 'location', 'creator']);
            }

            $location = Location::query()->lockForUpdate()->find($data['location_id']);
            if (!$location || !$location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'Choose an active location.']);
            }
            $purpose = InventoryReservationPurpose::query()->where('key', 'customer_order')->where('is_active', true)->first();
            if (!$purpose) {
                throw ValidationException::withMessages(['items' => 'The active “Customer order” reservation purpose is required before creating orders.']);
            }

            $order = SalesOrder::query()->create([
                'order_number' => 'MS-' . now()->format('ymd') . '-' . Str::upper(Str::random(6)),
                'status' => 'pending',
                'channel' => $data['channel'] ?? 'backoffice',
                'location_id' => $location->id,
                'customer_name' => trim($data['customer_name']),
                'customer_email' => isset($data['customer_email']) ? trim($data['customer_email']) : null,
                'customer_phone' => isset($data['customer_phone']) ? trim($data['customer_phone']) : null,
                'currency' => 'BDT',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $subtotal = '0.00';
            foreach ($data['items'] as $line) {
                // Price is always sourced from the active catalog, never trusted from the client.
                $variant = ProductVariant::query()->with('product')->lockForUpdate()->find($line['product_variant_id']);
                if (!$variant || $variant->status !== 'active' || !$variant->product || $variant->product->status !== 'active') {
                    throw ValidationException::withMessages(['items' => 'One of the selected products is no longer available for sale.']);
                }
                $qty = number_format((float) $line['quantity'], 4, '.', '');
                $unitPrice = $variant->effectiveSellingPrice();
                if (bccomp($unitPrice, '0', 2) < 0) {
                    throw ValidationException::withMessages(['items' => 'Product price must not be negative.']);
                }
                $lineTotal = bcadd('0', bcmul($qty, $unitPrice, 2), 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $item = SalesOrderItem::query()->create([
                    'sales_order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku ?: $variant->product->sku,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                ]);

                $reservation = $this->reservations->reserve([
                    'product_variant_id' => $variant->id,
                    'location_id' => $location->id,
                    'quantity' => $qty,
                    'purpose' => $purpose->key,
                    'reference_type' => 'sales_order',
                    'reference_id' => (string) $order->id,
                    'reason' => 'Sales order ' . $order->order_number,
                    'expires_at' => now()->addHours(72),
                ], $actor);
                $item->reservation_id = $reservation->id;
                $item->save();
            }

            $order->subtotal = $subtotal;
            $order->grand_total = $subtotal;
            $order->save();

            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending',
                'reason' => 'Order created and inventory reserved.', 'metadata' => ['item_count' => count($data['items'])],
                'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            $this->risk->assess($order);
            return $order->load(['items', 'location', 'creator', 'statusHistory.actor', 'riskAssessments']);
        }, 3);
    }

    public function cancel(SalesOrder $order, User $actor, string $reason): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled') return $locked->load(['items', 'location', 'statusHistory.actor']);
            if (!in_array($locked->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending or confirmed orders can be cancelled.']);
            }

            $reservations = InventoryReservation::query()
                ->where('reference_type', 'sales_order')
                ->where('reference_id', (string) $locked->id)
                ->where('status', 'active')->lockForUpdate()->get();

            foreach ($reservations as $reservation) {
                $this->reservations->resolve($reservation, 'cancel', $actor);
            }

            $from = $locked->status;
            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->save();

            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id, 'from_status' => $from, 'to_status' => 'cancelled',
                'reason' => $reason, 'metadata' => ['released_reservations' => $reservations->count()],
                'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            return $locked->load(['items', 'location', 'statusHistory.actor']);
        }, 3);
    }

    public function fulfill(SalesOrder $order, array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $data, $actor): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (!in_array($locked->status, ['confirmed', 'processing', 'partially_fulfilled'], true)) {
                throw ValidationException::withMessages(['status' => 'Only confirmed orders with an active workflow can be fulfilled.']);
            }

            if (!empty($data['idempotency_key'])) {
                $existing = SalesOrderFulfillment::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    if ((int) $existing->sales_order_id !== (int) $locked->id) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This request key has already been used for another fulfillment.']);
                    }
                    return $locked->load(['items', 'location', 'statusHistory.actor', 'fulfillments.items']);
                }
            }

            $items = SalesOrderItem::query()->where('sales_order_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
            $requested = collect($data['items'])->keyBy(fn (array $line): int => (int) $line['sales_order_item_id']);
            $fulfillment = SalesOrderFulfillment::query()->create([
                'fulfillment_number' => 'FUL-' . now()->format('ymd') . '-' . Str::upper(Str::random(8)),
                'sales_order_id' => $locked->id,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'fulfilled_at' => now(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($requested as $itemId => $line) {
                /** @var SalesOrderItem|null $item */
                $item = $items->get((int) $itemId);
                if (!$item) throw ValidationException::withMessages(['items' => 'A selected item does not belong to this order.']);
                $qty = number_format((float) $line['quantity'], 4, '.', '');
                $alreadyFulfilled = SalesOrderFulfillmentItem::query()
                    ->where('sales_order_item_id', $item->id)->sum('quantity');
                $remaining = bcsub((string) $item->quantity, (string) $alreadyFulfilled, 4);
                if (bccomp($qty, '0', 4) <= 0 || bccomp($qty, $remaining, 4) > 0) {
                    throw ValidationException::withMessages(['items' => "Fulfillment quantity for {$item->product_name} exceeds the remaining quantity ({$remaining})."]);
                }
                if (!$item->reservation_id) throw ValidationException::withMessages(['items' => 'An order item is missing its stock reservation.']);
                $reservation = InventoryReservation::query()->lockForUpdate()->find($item->reservation_id);
                if (!$reservation || $reservation->status !== 'active') {
                    throw ValidationException::withMessages(['items' => 'A stock reservation is no longer active. Review or re-reserve stock before fulfillment.']);
                }
                $this->reservations->fulfillPartial($reservation, $qty, $actor);
                SalesOrderFulfillmentItem::query()->create([
                    'sales_order_fulfillment_id' => $fulfillment->id,
                    'sales_order_item_id' => $item->id,
                    'quantity' => $qty,
                ]);
            }

            $allFulfilled = true;
            $anyFulfilled = false;
            foreach ($items as $item) {
                $done = (string) SalesOrderFulfillmentItem::query()->where('sales_order_item_id', $item->id)->sum('quantity');
                if (bccomp($done, '0', 4) > 0) $anyFulfilled = true;
                if (bccomp($done, (string) $item->quantity, 4) < 0) $allFulfilled = false;
            }
            $from = $locked->status;
            $locked->status = $allFulfilled ? 'fulfilled' : ($anyFulfilled ? 'partially_fulfilled' : 'processing');
            $locked->save();
            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id, 'from_status' => $from, 'to_status' => $locked->status,
                'reason' => 'Order fulfillment ' . $fulfillment->fulfillment_number . ' recorded.',
                'metadata' => ['fulfillment_id' => $fulfillment->id, 'fulfillment_number' => $fulfillment->fulfillment_number],
                'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            return $locked->load(['items', 'location', 'statusHistory.actor', 'fulfillments.items']);
        }, 3);
    }

    public function confirm(SalesOrder $order, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'confirmed') return $locked->load(['items', 'location']);
            if ($locked->status !== 'pending') throw ValidationException::withMessages(['status' => 'Only pending orders can be confirmed.']);

            $activeCount = InventoryReservation::query()
                ->where('reference_type', 'sales_order')->where('reference_id', (string) $locked->id)
                ->where('status', 'active')->count();
            if ($activeCount !== $locked->items()->count()) {
                throw ValidationException::withMessages(['status' => 'One or more stock reservations have expired or been released. Recreate the order before confirming.']);
            }

            $locked->status = 'confirmed';
            $locked->confirmed_at = now();
            $locked->save();
            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id, 'from_status' => 'pending', 'to_status' => 'confirmed',
                'reason' => 'Order confirmed.', 'changed_by' => $actor->id, 'created_at' => now(),
            ]);
            return $locked->load(['items', 'location', 'statusHistory.actor']);
        }, 3);
    }
}
