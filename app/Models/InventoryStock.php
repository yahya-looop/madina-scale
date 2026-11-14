<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int $location_id
 * @property numeric-string $quantity
 * @property numeric-string $reserved_quantity
 * @property numeric-string|null $low_stock_threshold
 * @property numeric-string|null $average_unit_cost
 * @property numeric-string|null $inventory_value
 * @property string $valuation_status
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 * @property-read Location $location
 */
class InventoryStock extends Model
{
    protected $fillable = [
        'average_unit_cost',
        'inventory_value',
        'valuation_status',
        'product_id',
        'product_variant_id',
        'location_id',
        'quantity',
        'reserved_quantity',
        'low_stock_threshold',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'location_id' => 'integer',
            'quantity' => 'decimal:4',
            'reserved_quantity' => 'decimal:4',
            'low_stock_threshold' => 'decimal:4',
            'average_unit_cost' => 'decimal:6',
            'inventory_value' => 'decimal:2',
        ];
    }

    /** @return HasMany<InventoryValuationMovement, $this> */
    public function valuationMovements(): HasMany
    {
        return $this->hasMany(InventoryValuationMovement::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return numeric-string */
    public function getAvailableQuantityAttribute(): string
    {
        return bcsub(
            (string) $this->quantity,
            (string) $this->reserved_quantity,
            4
        );
    }

    public function getIsLowStockAttribute(): bool
    {
        return bccomp(
            (string) ($this->low_stock_threshold ?? '0'),
            '0',
            4
        ) > 0
            && bccomp(
                (string) $this->available_quantity,
                (string) ($this->low_stock_threshold ?? '0'),
                4
            ) <= 0;
    }
}
