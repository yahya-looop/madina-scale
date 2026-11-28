<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

/** Keeps order adjustment totals consistent with the original pricing engine snapshots. */
class SalesOrderAdjustmentPricingRules
{
    /**
     * @param list<array{id:int, quantity:string, unit_price:string, line_discount:string}> $lines
     * @param array<string, mixed> $snapshot
     * @return array{subtotal:string, discount_total:string, net_line_subtotal:string, final_line_subtotal:string, lines:list<array{id:int, gross:string, line_discount:string, allocated_order_discount:string, line_total:string}>, snapshot:array<string,mixed>}
     */
    public function reconcile(array $lines, array $snapshot, string $currentDiscount, ?string $requestedDiscount): array
    {
        $subtotal = '0.00';
        $lineDiscountTotal = '0.00';
        $workingLines = [];

        foreach ($lines as $line) {
            $gross = bcmul((string) $line['quantity'], (string) $line['unit_price'], 2);
            $lineDiscount = bcadd((string) $line['line_discount'], '0', 2);
            if (bccomp($lineDiscount, $gross, 2) > 0) {
                throw ValidationException::withMessages([
                    'items' => 'An adjusted product price cannot be lower than its already-applied line discount.',
                ]);
            }

            $netBeforeOrderDiscount = bcsub($gross, $lineDiscount, 2);
            $subtotal = bcadd($subtotal, $gross, 2);
            $lineDiscountTotal = bcadd($lineDiscountTotal, $lineDiscount, 2);
            $workingLines[] = [
                'id' => (int) $line['id'],
                'gross' => $gross,
                'line_discount' => $lineDiscount,
                'net_before_order_discount' => $netBeforeOrderDiscount,
            ];
        }

        $netLineSubtotal = bcsub($subtotal, $lineDiscountTotal, 2);
        $isB2bQuote = ($snapshot['price_basis'] ?? null) === 'b2b_quote';

        if ($isB2bQuote) {
            $discountTotal = $requestedDiscount === null
                ? bcadd($currentDiscount, '0', 2)
                : bcadd($requestedDiscount, '0', 2);
            $quoteDiscount = bcsub($discountTotal, $lineDiscountTotal, 2);
            if (bccomp($quoteDiscount, '0.00', 2) < 0) {
                throw ValidationException::withMessages([
                    'discount_total' => 'The order discount cannot be lower than the accepted quotation line discounts.',
                ]);
            }
            $snapshot['accepted_line_subtotal'] = $netLineSubtotal;
            $snapshot['gross_subtotal'] = $subtotal;
            $snapshot['line_discount_total'] = $lineDiscountTotal;
            $snapshot['accepted_discount'] = $quoteDiscount;
        } else {
            $promotionDiscount = bcadd((string) ($snapshot['promotion_discount'] ?? '0.00'), '0', 2);
            $manualDiscount = bcadd((string) ($snapshot['manual_discount'] ?? '0.00'), '0', 2);
            if ($requestedDiscount !== null) {
                $manualDiscount = bcsub((string) $requestedDiscount, $promotionDiscount, 2);
                if (bccomp($manualDiscount, '0.00', 2) < 0) {
                    throw ValidationException::withMessages([
                        'discount_total' => 'The order discount cannot be lower than the applied promotion discount.',
                    ]);
                }
            }
            $discountTotal = bcadd($promotionDiscount, $manualDiscount, 2);
            $snapshot['manual_discount'] = $manualDiscount;
            $snapshot['promotion_discount'] = $promotionDiscount;
        }

        if (bccomp($discountTotal, $subtotal, 2) > 0) {
            throw ValidationException::withMessages(['discount_total' => 'Discount cannot exceed the adjusted item subtotal.']);
        }
        if (bccomp($discountTotal, $lineDiscountTotal, 2) < 0) {
            throw ValidationException::withMessages([
                'discount_total' => 'The total order discount cannot be lower than the discounts already allocated to order lines.',
            ]);
        }

        // line_discount is already allocated to each item. Allocate only the
        // remaining order-level discount across the remaining line values.
        $remainingOrderDiscount = bcsub($discountTotal, $lineDiscountTotal, 2);
        $allocations = $this->allocateDiscount($workingLines, $remainingOrderDiscount, $netLineSubtotal);
        $reconciledLines = [];
        $finalLineSubtotal = '0.00';

        foreach ($workingLines as $index => $line) {
            $allocated = $allocations[$index];
            $lineTotal = bcsub($line['net_before_order_discount'], $allocated, 2);
            $finalLineSubtotal = bcadd($finalLineSubtotal, $lineTotal, 2);
            $reconciledLines[] = [
                'id' => $line['id'],
                'gross' => $line['gross'],
                'line_discount' => $line['line_discount'],
                'allocated_order_discount' => $allocated,
                'line_total' => $lineTotal,
            ];
        }

        $expectedFinalLineSubtotal = bcsub($subtotal, $discountTotal, 2);
        if (bccomp($finalLineSubtotal, $expectedFinalLineSubtotal, 2) !== 0) {
            throw new \LogicException('Allocated order line totals do not reconcile with subtotal less total discount.');
        }

        $snapshot['total_discount'] = $discountTotal;
        $snapshot['line_discount_total'] = $lineDiscountTotal;
        $snapshot['allocated_order_discount'] = $remainingOrderDiscount;

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'net_line_subtotal' => $netLineSubtotal,
            'final_line_subtotal' => $finalLineSubtotal,
            'lines' => $reconciledLines,
            'snapshot' => $snapshot,
        ];
    }

    /**
     * Allocate a monetary amount proportionally in integer cents. Any rounding
     * remainder is distributed one cent at a time in stable line order.
     *
     * @param list<array{net_before_order_discount:string}> $lines
     * @return list<string>
     */
    private function allocateDiscount(array $lines, string $discount, string $netSubtotal): array
    {
        $allocations = array_fill(0, count($lines), '0.00');
        if (bccomp($discount, '0.00', 2) === 0) {
            return $allocations;
        }
        if (bccomp($netSubtotal, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['discount_total' => 'A discount cannot be allocated when the net item subtotal is zero.']);
        }

        $discountCents = (int) bcmul($discount, '100', 0);
        $netCents = (int) bcmul($netSubtotal, '100', 0);
        $allocatedCents = 0;
        $remainders = [];

        foreach ($lines as $index => $line) {
            $lineCents = (int) bcmul($line['net_before_order_discount'], '100', 0);
            $numerator = $discountCents * $lineCents;
            $share = intdiv($numerator, $netCents);
            $allocations[$index] = bcdiv((string) $share, '100', 2);
            $allocatedCents += $share;
            $remainders[] = ['index' => $index, 'remainder' => $numerator % $netCents, 'capacity' => $lineCents - $share];
        }

        $leftover = $discountCents - $allocatedCents;
        usort($remainders, static fn (array $a, array $b): int => ($b['remainder'] <=> $a['remainder']) ?: ($a['index'] <=> $b['index']));
        while ($leftover > 0) {
            $progress = false;
            foreach ($remainders as $entry) {
                if ($leftover === 0) {
                    break;
                }
                $index = $entry['index'];
                $currentCents = (int) bcmul($allocations[$index], '100', 0);
                $lineCents = (int) bcmul($lines[$index]['net_before_order_discount'], '100', 0);
                if ($currentCents >= $lineCents) {
                    continue;
                }
                $allocations[$index] = bcdiv((string) ($currentCents + 1), '100', 2);
                $leftover--;
                $progress = true;
            }
            if (! $progress) {
                throw ValidationException::withMessages(['discount_total' => 'The order discount cannot be allocated across the available item values.']);
            }
        }

        return $allocations;
    }
}
