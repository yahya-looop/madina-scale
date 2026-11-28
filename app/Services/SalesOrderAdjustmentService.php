<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderAdjustment;
use App\Models\SalesOrderShipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesOrderAdjustmentService
{
    public function __construct(
        private readonly ActivityLogService $activityLog,
        private readonly SalesOrderAdjustmentPricingRules $pricingRules,
    ) {}

    /** Apply an audited order-specific commercial adjustment. */
    public function apply(SalesOrder $order, array $data, User $actor): SalesOrderAdjustment
    {
        return DB::transaction(function () use ($order, $data, $actor): SalesOrderAdjustment {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'Cancelled orders cannot receive commercial adjustments.']);
            }
            if ($locked->b2b_quote_id && $locked->b2bQuote()->whereNotNull('converted_order_id')->exists()) {
                // Adjustments to converted quotes remain possible only through this audited workflow.
            }

            $items = $locked->items()->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw ValidationException::withMessages(['order' => 'An order without line items cannot be adjusted.']);
            }

            $before = [
                'subtotal' => (string) $locked->subtotal,
                'discount_total' => (string) $locked->discount_total,
                'tax_total' => (string) $locked->tax_total,
                'delivery_charge' => (string) ($locked->delivery_charge ?? '0.00'),
                'grand_total' => (string) $locked->grand_total,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id, 'product_name' => $item->product_name,
                    'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values()->all(),
            ];

            $activeShipment = SalesOrderShipment::query()
                ->where('sales_order_id', $locked->id)
                ->whereNotIn('status', ['failed', 'cancelled', 'delivered', 'returned'])
                ->lockForUpdate()->first();
            if ($activeShipment) {
                throw ValidationException::withMessages([
                    'adjustment' => 'A courier shipment is already booked or in transit. Complete the provider cancellation/rebook workflow before changing prices, discount, delivery charge or COD amount.',
                ]);
            }

            foreach (($data['items'] ?? []) as $line) {
                $item = $items->firstWhere('id', (int) $line['id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'An adjusted order item does not belong to this order.']);
                }
                $unitPrice = bcadd((string) $line['unit_price'], '0', 2);
                $itemSnapshot = $item->pricing_snapshot ?? [];
                $lineDiscount = bcadd((string) ($itemSnapshot['line_discount'] ?? '0.00'), '0', 2);
                $gross = bcmul((string) $item->quantity, $unitPrice, 2);
                if (bccomp($lineDiscount, $gross, 2) > 0) {
                    throw ValidationException::withMessages(['items' => 'An adjusted product price cannot be lower than its already-applied line discount.']);
                }
                $itemSnapshot = array_merge($itemSnapshot, [
                    'last_order_adjustment_at' => now()->toIso8601String(),
                    'last_order_adjustment_by' => $actor->id,
                    'catalogue_price_unchanged' => true,
                ]);
                $item->update([
                    'unit_price' => $unitPrice,
                    'line_total' => bcsub($gross, $lineDiscount, 2),
                    'pricing_snapshot' => $itemSnapshot,
                ]);
            }

            $items = $locked->items()->orderBy('id')->get();
            $pricingSnapshot = $locked->pricing_snapshot ?? [];
            $pricingLines = $items->map(fn ($item) => [
                'id' => (int) $item->id,
                'quantity' => (string) $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_discount' => (string) (($item->pricing_snapshot ?? [])['line_discount'] ?? '0.00'),
            ])->values()->all();
            $reconciled = $this->pricingRules->reconcile(
                $pricingLines,
                $pricingSnapshot,
                (string) $locked->discount_total,
                array_key_exists('discount_total', $data) ? (string) $data['discount_total'] : null,
            );

            foreach ($reconciled['lines'] as $line) {
                $item = $items->firstWhere('id', $line['id']);
                if (! $item) {
                    throw new \LogicException('A reconciled order line could not be matched to its order item.');
                }
                $itemSnapshot = $item->pricing_snapshot ?? [];
                $itemSnapshot['allocated_order_discount'] = $line['allocated_order_discount'];
                $itemSnapshot['final_line_total'] = $line['line_total'];
                if (($pricingSnapshot['price_basis'] ?? null) === 'b2b_quote') {
                    $itemSnapshot['accepted_line_total'] = $line['line_total'];
                }
                $item->update([
                    'line_total' => $line['line_total'],
                    'pricing_snapshot' => $itemSnapshot,
                ]);
            }

            $items = $locked->items()->orderBy('id')->get();
            $subtotal = $reconciled['subtotal'];
            $discount = $reconciled['discount_total'];
            $pricingSnapshot = $reconciled['snapshot'];
            $delivery = array_key_exists('delivery_charge', $data)
                ? bcadd((string) $data['delivery_charge'], '0', 2)
                : bcadd((string) ($locked->delivery_charge ?? '0.00'), '0', 2);
            $tax = bcadd((string) $locked->tax_total, '0', 2);

            $lineTotals = $items->reduce(
                fn (string $sum, $item): string => bcadd($sum, (string) $item->line_total, 2),
                '0.00',
            );
            $newTotal = bcadd(bcadd($lineTotals, $tax, 2), $delivery, 2);
            $expectedTotal = bcadd(bcadd(bcsub($subtotal, $discount, 2), $tax, 2), $delivery, 2);
            if (bccomp($newTotal, $expectedTotal, 2) !== 0) {
                throw new \LogicException('Sales order line totals do not reconcile with the order grand total.');
            }

            $pricingSnapshot = array_merge($pricingSnapshot, [
                'last_adjustment_at' => now()->toIso8601String(),
                'last_adjustment_by' => $actor->id,
                'adjustment_reason' => trim($data['reason']),
                'total_discount' => $discount,
            ]);
            if (($pricingSnapshot['price_basis'] ?? null) === 'b2b_quote') {
                $pricingSnapshot['accepted_tax'] = $tax;
                $pricingSnapshot['accepted_total'] = $newTotal;
            }
            $previousTotal = bcadd((string) $locked->grand_total, '0', 2);
            $delta = bcsub($newTotal, $previousTotal, 2);

            $locked->update([
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'delivery_charge' => $delivery,
                'grand_total' => $newTotal,
                'pricing_snapshot' => $pricingSnapshot,
            ]);

            $allocatedPayments = (string) DB::table('payment_allocations')
                ->where('sales_order_id', $locked->id)
                ->sum('amount');
            $hasReceivedPayment = bccomp($allocatedPayments, '0.00', 2) > 0;
            $settlement = bccomp($delta, '0.00', 2) > 0
                ? 'collection_due'
                : (bccomp($delta, '0.00', 2) < 0 && $hasReceivedPayment ? 'customer_credit_due' : 'not_required');

            $after = [
                'subtotal' => $subtotal, 'discount_total' => $discount,
                'tax_total' => $tax, 'delivery_charge' => $delivery,
                'grand_total' => $newTotal,
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id, 'product_name' => $item->product_name,
                    'quantity' => (string) $item->quantity, 'unit_price' => (string) $item->unit_price,
                    'line_total' => (string) $item->line_total,
                ])->values()->all(),
            ];

            $adjustment = SalesOrderAdjustment::query()->create([
                'sales_order_id' => $locked->id,
                'created_by' => $actor->id,
                'adjustment_number' => 'ADJ-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'reason' => trim($data['reason']),
                'before_snapshot' => $before,
                'after_snapshot' => $after,
                'previous_total' => $previousTotal,
                'new_total' => $newTotal,
                'financial_delta' => $delta,
                'settlement_status' => $settlement,
                'courier_sync_status' => array_key_exists('delivery_charge', $data) ? 'not_booked' : 'not_required',
            ]);

            $this->activityLog->record(
                'adjusted', 'orders',
                'Applied '.$adjustment->adjustment_number.' to order '.$locked->order_number,
                $adjustment,
                $before,
                $after,
                ['reason' => $adjustment->reason, 'financial_delta' => $delta, 'settlement_status' => $settlement],
                $actor,
            );

            return $adjustment->load('actor:id,name');
        }, 3);
    }
}
