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
    public function __construct(private readonly ActivityLogService $activityLog) {}

    /**
     * Apply a controlled, order-specific price/discount/delivery adjustment.
     * Product catalogue prices are never touched. Every applied change creates
     * a permanent before/after snapshot and a reconciliation obligation.
     *
     * @param array<string, mixed> $data
     */
    public function apply(SalesOrder $order, array $data, User $actor): SalesOrderAdjustment
    {
        return DB::transaction(function () use ($order, $data, $actor): SalesOrderAdjustment {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled') {
                throw ValidationException::withMessages(['order' => 'Cancelled orders cannot receive commercial adjustments.']);
            }
            if ($locked->b2b_quote_id && $locked->b2bQuote()->whereNotNull('converted_order_id')->exists()) {
                // A converted quote is the commercial source document. An order adjustment
                // remains possible only through this explicit audited workflow.
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
                $unitPrice = number_format((float) $line['unit_price'], 2, '.', '');
                $lineTotal = bcmul((string) $item->quantity, $unitPrice, 2);
                $item->update([
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'pricing_snapshot' => array_merge($item->pricing_snapshot ?? [], [
                        'last_order_adjustment_at' => now()->toIso8601String(),
                        'last_order_adjustment_by' => $actor->id,
                        'catalogue_price_unchanged' => true,
                    ]),
                ]);
            }

            $items = $locked->items()->orderBy('id')->get();
            $subtotal = '0.00';
            foreach ($items as $item) {
                $subtotal = bcadd($subtotal, (string) $item->line_total, 2);
            }
            $discount = array_key_exists('discount_total', $data)
                ? number_format((float) $data['discount_total'], 2, '.', '')
                : (string) $locked->discount_total;
            if (bccomp($discount, $subtotal, 2) > 0) {
                throw ValidationException::withMessages(['discount_total' => 'Discount cannot exceed the adjusted item subtotal.']);
            }
            $delivery = array_key_exists('delivery_charge', $data)
                ? number_format((float) $data['delivery_charge'], 2, '.', '')
                : number_format((float) ($locked->delivery_charge ?? 0), 2, '.', '');
            $tax = number_format((float) $locked->tax_total, 2, '.', '');
            $newTotal = bcadd(bcadd(bcsub($subtotal, $discount, 2), $tax, 2), $delivery, 2);
            $previousTotal = number_format((float) $locked->grand_total, 2, '.', '');
            $delta = bcsub($newTotal, $previousTotal, 2);

            $locked->update([
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'delivery_charge' => $delivery,
                'grand_total' => $newTotal,
                'pricing_snapshot' => array_merge($locked->pricing_snapshot ?? [], [
                    'last_adjustment_at' => now()->toIso8601String(),
                    'last_adjustment_by' => $actor->id,
                    'adjustment_reason' => trim($data['reason']),
                ]),
            ]);

            // payment_status is a checkout/bank-transfer workflow state, not the
            // authoritative amount-paid ledger. Use posted payment allocations so
            // partial receipts and cash/COD reconciliations are not missed.
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
