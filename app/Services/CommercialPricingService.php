<?php

namespace App\Services;

use App\Models\B2BAccount;
use App\Models\FlashSale;
use App\Models\FlashSaleItem;
use App\Models\PricingPromotion;
use App\Models\PricingPromotionRedemption;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderFlashSaleAllocation;
use App\Models\SalesOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class CommercialPricingService
{
    /**
     * Resolve a catalogue line inside the caller's transaction. Flash-sale quota is
     * reserved atomically by incrementing quantity_sold while the item row is locked.
     */
    public function priceLine(ProductVariant $variant, string $quantity, ?B2BAccount $account): array
    {
        $catalogue = $variant->effectiveSellingPrice();
        $unitPrice = $catalogue;
        $source = 'retail';
        if ($account && $account->status === 'active') {
            if ($variant->wholesale_price !== null && bccomp((string) $variant->wholesale_price, '0', 2) > 0) {
                $unitPrice = (string) $variant->wholesale_price;
                $source = 'wholesale';
            } elseif ((int) $account->default_discount_percent > 0) {
                $unitPrice = bcsub($catalogue, bcdiv(bcmul($catalogue, (string) $account->default_discount_percent, 2), '100', 2), 2);
                $source = 'b2b_discount';
            }
        }

        $flash = null;
        // Flash-sale quota is integral. Fractional-quantity products retain their
        // normal/tier price rather than consuming an inaccurate integer allocation.
        if (bccomp($quantity, number_format((int) $quantity, 4, '.', ''), 4) === 0
            && (int) $quantity > 0) {
            $flash = FlashSaleItem::query()
                ->where('product_variant_id', $variant->id)
                ->whereHas('sale', fn ($q) => $q->where('status', 'active')
                    ->where('starts_at', '<=', now())->where('ends_at', '>=', now()))
                ->with('sale')
                ->orderBy('sale_price')->orderBy('id')
                ->lockForUpdate()->get()
                ->first(function (FlashSaleItem $item) use ($quantity): bool {
                    return $item->sale
                        && ($item->quantity_limit === null
                            || ($item->quantity_sold + (int) $quantity) <= $item->quantity_limit);
                });
        }

        if ($flash && bccomp((string) $flash->sale_price, $unitPrice, 2) < 0) {
            $unitPrice = (string) $flash->sale_price;
            $source = 'flash_sale';
            $flash->quantity_sold += (int) $quantity;
            $flash->save();
        } else {
            $flash = null;
        }

        return [
            'unit_price' => number_format((float) $unitPrice, 2, '.', ''),
            'catalogue_price' => number_format((float) $catalogue, 2, '.', ''),
            'price_source' => $source,
            'flash_sale_item' => $flash,
            'flash_sale_regular_price' => $flash ? (string) $flash->regular_price : null,
        ];
    }

    /**
     * Apply eligible product/variant/category and order promotions. Code promotions
     * are explicit; publicly-visible promotions are automatic. Promotion rows are
     * locked before limits are checked so concurrent orders cannot oversubscribe them.
     *
     * @param array<int, array{item: SalesOrderItem, variant: ProductVariant, gross: string, snapshot: array}> $lines
     * @return array{discount_total:string, order_discount:string, promotion_id:?int, promotion_code:?string}
     */
    public function applyPromotions(SalesOrder $order, array $lines, ?int $customerId, ?string $code): array
    {
        $subtotal = '0.00';
        foreach ($lines as $line) {
            $subtotal = bcadd($subtotal, $line['gross'], 2);
        }

        $normalizedCode = filled($code) ? mb_strtoupper(trim($code)) : null;
        $promotions = PricingPromotion::query()
            ->where('status', 'active')
            ->where(fn ($q) => $q->where(function ($q) use ($normalizedCode) {
                if ($normalizedCode !== null) $q->where('code', $normalizedCode);
                else $q->whereRaw('1 = 0');
            })->orWhere(fn ($q) => $q->whereNull('code')->where('publicly_visible', true)))
            ->orderBy('priority')->orderBy('id')->lockForUpdate()->get()
            ->filter(fn (PricingPromotion $p) => $p->isCurrentlyActive())
            ->sortBy(fn (PricingPromotion $p) => $normalizedCode !== null
                && mb_strtoupper((string) $p->code) === $normalizedCode ? 0 : 1)
            ->values();

        if ($normalizedCode !== null && ! $promotions->contains(fn ($p) => mb_strtoupper((string) $p->code) === $normalizedCode)) {
            throw ValidationException::withMessages(['promotion_code' => 'This promotion code is invalid, expired, exhausted, or unavailable.']);
        }

        $used = [];
        $lineDiscountTotal = '0.00';
        foreach ($lines as &$line) {
            $eligible = $promotions->filter(function (PricingPromotion $p) use ($line, $subtotal, $customerId): bool {
                if ($p->scope === 'order') return false;
                if (bccomp($subtotal, (string) $p->minimum_order_amount, 2) < 0
                    || bccomp((string) $line['item']->quantity, (string) $p->minimum_quantity, 3) < 0) return false;
                $variant = $line['variant'];
                $matches = match ($p->scope) {
                    'product' => (int) $p->product_id === (int) $variant->product_id,
                    'variant' => (int) $p->product_variant_id === (int) $variant->id,
                    'category' => (int) $p->product_category_id === (int) $variant->product?->category_id,
                    default => false,
                };
                return $matches && $this->withinCustomerLimit($p, $customerId);
            });

            $chosen = $this->selectPromotions($eligible);
            $discount = '0.00';
            $linePromotions = [];
            foreach ($chosen as $promotion) {
                $remaining = bcsub($line['gross'], $discount, 2);
                $amount = $this->discount($promotion, $remaining);
                $discount = bcadd($discount, $amount, 2);
                $used[$promotion->id] = ($used[$promotion->id] ?? ['promotion' => $promotion, 'discount' => '0.00']);
                $used[$promotion->id]['discount'] = bcadd($used[$promotion->id]['discount'], $amount, 2);
                $linePromotions[] = ['promotion_id' => $promotion->id, 'name' => $promotion->name, 'discount' => $amount];
                if (! $promotion->stackable) break;
            }
            $line['snapshot']['line_promotions'] = $linePromotions;
            $line['snapshot']['line_discount'] = $discount;
            $lineDiscountTotal = bcadd($lineDiscountTotal, $discount, 2);
            $line['item']->line_total = bcsub($line['gross'], $discount, 2);
            $line['item']->pricing_snapshot = $line['snapshot'];
            $line['item']->save();
        }
        unset($line);

        $netSubtotal = bcsub($subtotal, $lineDiscountTotal, 2);
        $totalQuantity = '0.0000';
        foreach ($lines as $line) $totalQuantity = bcadd($totalQuantity, (string) $line['item']->quantity, 4);
        $linePromotionsCanStack = collect($used)->every(fn (array $entry) => $entry['promotion']->stackable);
        $orderPromotions = $promotions->filter(fn (PricingPromotion $p) => $p->scope === 'order'
            && (! $used || ($linePromotionsCanStack && $p->stackable))
            && bccomp($netSubtotal, (string) $p->minimum_order_amount, 2) >= 0
            && bccomp($totalQuantity, (string) $p->minimum_quantity, 3) >= 0
            && $this->withinCustomerLimit($p, $customerId));
        $orderChosen = $this->selectPromotions($orderPromotions);
        $orderDiscount = '0.00';
        foreach ($orderChosen as $promotion) {
            $amount = $this->discount($promotion, bcsub($netSubtotal, $orderDiscount, 2));
            $orderDiscount = bcadd($orderDiscount, $amount, 2);
            $used[$promotion->id] = ($used[$promotion->id] ?? ['promotion' => $promotion, 'discount' => '0.00']);
            $used[$promotion->id]['discount'] = bcadd($used[$promotion->id]['discount'], $amount, 2);
            if (! $promotion->stackable) break;
        }

        if ($normalizedCode !== null && ! collect($used)->contains(
            fn (array $entry) => mb_strtoupper((string) $entry['promotion']->code) === $normalizedCode
        )) {
            throw ValidationException::withMessages(['promotion_code' => 'This promotion code does not apply to the selected order items or customer.']);
        }

        foreach ($used as $entry) {
            /** @var PricingPromotion $promotion */
            $promotion = $entry['promotion'];
            $redemption = PricingPromotionRedemption::query()->firstOrNew([
                'pricing_promotion_id' => $promotion->id,
                'sales_order_id' => $order->id,
            ]);
            $wasAlreadyApplied = $redemption->exists && $redemption->status === 'applied';
            $redemption->fill([
                'customer_id' => $customerId,
                'promotion_code' => $promotion->code,
                'discount_amount' => $entry['discount'],
                'status' => 'applied',
                'redeemed_at' => now(),
                'released_at' => null,
            ]);
            $redemption->save();
            if (! $wasAlreadyApplied) {
                $promotion->increment('usage_count');
            }
        }

        $discountTotal = bcadd($lineDiscountTotal, $orderDiscount, 2);
        $primary = $orderChosen->first();
        if (! $primary && $used !== []) {
            $firstUsed = reset($used);
            $primary = $firstUsed['promotion'];
        }

        return [
            'discount_total' => $discountTotal,
            'order_discount' => $orderDiscount,
            'promotion_id' => $primary?->id,
            'promotion_code' => $normalizedCode,
            'applied_promotions' => collect($used)->map(fn (array $entry): array => [
                'promotion_id' => $entry['promotion']->id,
                'name' => $entry['promotion']->name,
                'code' => $entry['promotion']->code,
                'scope' => $entry['promotion']->scope,
                'discount' => $entry['discount'],
                'priority' => (int) $entry['promotion']->priority,
                'stackable' => (bool) $entry['promotion']->stackable,
            ])->values()->all(),
        ];
    }

    private function withinCustomerLimit(PricingPromotion $promotion, ?int $customerId): bool
    {
        if (! $promotion->per_customer_limit) return true;
        if (! $customerId) return false;
        $used = PricingPromotionRedemption::query()
            ->where('pricing_promotion_id', $promotion->id)
            ->where('customer_id', $customerId)
            ->where('status', 'applied')->count();
        return $used < $promotion->per_customer_limit;
    }

    private function selectPromotions(Collection $promotions): Collection
    {
        if ($promotions->isEmpty()) return collect();

        // Rules are already sorted by priority (then ID). Apply them in that
        // order and stop at the first non-stackable rule. This preserves a
        // higher-priority stackable rule instead of incorrectly skipping it.
        $selected = collect();
        foreach ($promotions as $promotion) {
            $selected->push($promotion);
            if (! $promotion->stackable) {
                break;
            }
        }

        return $selected;
    }

    private function discount(PricingPromotion $promotion, string $base): string
    {
        if (bccomp($base, '0.00', 2) <= 0) return '0.00';
        if ($promotion->discount_type === 'percent') {
            $raw = bcdiv(bcmul($base, (string) $promotion->discount_value, 4), '100', 4);
            // BC Math truncates at the requested scale; add half a cent at
            // four-decimal precision before reducing to two decimals (half-up).
            $amount = bcadd($raw, '0.0050', 4);
            $amount = bcdiv($amount, '1', 2);
        } else {
            $amount = number_format((float) $promotion->discount_value, 2, '.', '');
        }
        return bccomp($amount, $base, 2) > 0 ? $base : $amount;
    }

    public function releaseOrder(SalesOrder $order): void
    {
        $allocations = SalesOrderFlashSaleAllocation::query()
            ->where('sales_order_id', $order->id)->whereIn('status', ['reserved', 'consumed'])
            ->lockForUpdate()->get();
        foreach ($allocations as $allocation) {
            $orderItem = $allocation->sales_order_item_id
                ? SalesOrderItem::query()->find($allocation->sales_order_item_id)
                : null;
            $flashItem = FlashSaleItem::query()->lockForUpdate()->find($allocation->flash_sale_item_id);
            if ($flashItem) {
                $flashItem->quantity_sold = max(0, $flashItem->quantity_sold - $allocation->quantity);
                $flashItem->save();
            }
            $allocation->status = 'released';
            $allocation->released_at = now();
            if ($orderItem) $allocation->order_item_snapshot = [
                'product_id' => $orderItem->product_id,
                'product_variant_id' => $orderItem->product_variant_id,
                'product_name' => $orderItem->product_name,
                'variant_name' => $orderItem->variant_name,
                'sku' => $orderItem->sku,
                'quantity' => (string) $orderItem->quantity,
            ];
            $allocation->sales_order_item_id = null;
            $allocation->save();
        }

        $redemptions = PricingPromotionRedemption::query()
            ->where('sales_order_id', $order->id)->where('status', 'applied')->lockForUpdate()->get();
        foreach ($redemptions as $redemption) {
            $promotion = PricingPromotion::query()->lockForUpdate()->find($redemption->pricing_promotion_id);
            if ($promotion) $promotion->usage_count = max(0, $promotion->usage_count - 1);
            $promotion?->save();
            $redemption->update(['status' => 'released', 'released_at' => now()]);
        }
    }

    public function consumeOrderFlashSales(SalesOrder $order): void
    {
        SalesOrderFlashSaleAllocation::query()->where('sales_order_id', $order->id)
            ->where('status', 'reserved')->lockForUpdate()->get()
            ->each(fn ($allocation) => $allocation->update(['status' => 'consumed', 'consumed_at' => now()]));
    }
}
