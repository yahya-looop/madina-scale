<?php

namespace App\Services;

use App\Models\FlashSaleItem;
use App\Models\PricingPromotion;
use Illuminate\Support\Collection;

/**
 * Central pricing calculations shared by storefront and commercial workflows.
 * All monetary amounts are calculated in integer minor units to avoid floating-point drift.
 */
class PricingEngine
{
    public function flashSalePriceForVariant(int $variantId): ?string
    {
        $item = FlashSaleItem::query()
            ->where('product_variant_id', $variantId)
            ->where(function ($query): void {
                $query->whereNull('quantity_limit')->orWhereColumn('quantity_sold', '<', 'quantity_limit');
            })
            ->whereHas('sale', function ($query): void {
                $query->where('status', 'active')
                    ->where('show_on_storefront', true)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now());
            })
            ->orderBy('sale_price')
            ->first();

        return $item?->sale_price;
    }

    /**
     * Return an explainable discount quote. This does not consume coupon usage;
     * usage must only be incremented after a successful order transaction.
     *
     * @param array{subtotal: int|float|string, code?: string|null, quantity?: int|float} $cart
     * @return array{subtotal: string, discount: string, total: string, promotion_id: int|null, code: string|null}
     */
    public function quoteOrderDiscount(array $cart): array
    {
        $subtotalMinor = max(0, (int) round(((float) ($cart['subtotal'] ?? 0)) * 100));
        $code = filled($cart['code'] ?? null) ? mb_strtoupper(trim((string) $cart['code'])) : null;
        $promotion = null;

        if ($code !== null) {
            $promotion = PricingPromotion::query()->where('code', $code)->first();
            if (! $promotion || ! $promotion->isCurrentlyActive() || $promotion->scope !== 'order') {
                $promotion = null;
            }
        }

        $discountMinor = 0;
        if ($promotion && $subtotalMinor >= (int) round(((float) $promotion->minimum_order_amount) * 100)) {
            $valueMinor = (int) round(((float) $promotion->discount_value) * 100);
            $discountMinor = $promotion->discount_type === 'percent'
                ? (int) round($subtotalMinor * min(10000, $valueMinor) / 10000)
                : min($subtotalMinor, $valueMinor);
        }

        return [
            'subtotal' => number_format($subtotalMinor / 100, 2, '.', ''),
            'discount' => number_format($discountMinor / 100, 2, '.', ''),
            'total' => number_format(max(0, $subtotalMinor - $discountMinor) / 100, 2, '.', ''),
            'promotion_id' => $promotion?->id,
            'code' => $promotion?->code,
        ];
    }
}
