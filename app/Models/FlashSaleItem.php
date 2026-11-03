<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $quantity_sold
 * @property int|null $quantity_limit
 * @property numeric-string $regular_price
 * @property numeric-string $sale_price
 * @property-read FlashSale|null $sale
 */
class FlashSaleItem extends Model
{
    protected $fillable = [
        'flash_sale_id', 'product_variant_id', 'regular_price', 'sale_price',
        'quantity_limit', 'quantity_sold', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'regular_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'quantity_limit' => 'integer',
            'quantity_sold' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<FlashSale, $this> */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return HasMany<SalesOrderFlashSaleAllocation, $this> */
    public function allocations(): HasMany
    {
        return $this->hasMany(SalesOrderFlashSaleAllocation::class, 'flash_sale_item_id');
    }

    public function remainingQuantity(): ?int
    {
        return $this->quantity_limit === null ? null : max(0, $this->quantity_limit - $this->quantity_sold);
    }
}
