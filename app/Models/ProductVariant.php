<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string $sku
 * @property string|null $barcode
 * @property numeric-string $cost_price
 * @property numeric-string $selling_price
 * @property numeric-string|null $wholesale_price
 * @property numeric-string|null $promotional_price
 * @property Carbon|null $promotion_starts_at
 * @property Carbon|null $promotion_ends_at
 * @property bool $is_default
 * @property string $status
 * @property int $sort_order
 * @property-read Product $product
 */
class ProductVariant extends Model
{
    /** @use HasFactory<Factory<ProductVariant>> */
    use HasFactory, SoftDeletes;

    protected $hidden = [
        'attribute_signature',
    ];

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'barcode',
        'cost_price',
        'selling_price',
        'wholesale_price',
        'promotional_price',
        'promotion_starts_at',
        'promotion_ends_at',
        'is_default',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'promotional_price' => 'decimal:2',
            'promotion_starts_at' => 'datetime',
            'promotion_ends_at' => 'datetime',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Attribute/value mapping records for this variant.
     */
    /** @return HasMany<ProductVariantAttributeValue, $this> */
    public function attributeValues(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttributeValue::class,
            'product_variant_id'
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Returns the effective selling price as a decimal string.
     */
    /** @return numeric-string */
    public function effectiveSellingPrice(): string
    {
        $now = now();

        $promotionIsActive =
            $this->promotional_price !== null
            && (
                $this->promotion_starts_at === null
                || $this->promotion_starts_at->lte($now)
            )
            && (
                $this->promotion_ends_at === null
                || $this->promotion_ends_at->gte($now)
            );

        return $promotionIsActive
            ? (string) $this->promotional_price
            : (string) $this->selling_price;
    }
}
