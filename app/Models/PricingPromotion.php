<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $code
 * @property string $name
 * @property string $scope
 * @property string $discount_type
 * @property numeric-string $discount_value
 * @property numeric-string $minimum_order_amount
 * @property numeric-string $minimum_quantity
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int|null $product_category_id
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property int|null $per_customer_limit
 * @property int $priority
 * @property bool $stackable
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 */
class PricingPromotion extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'discount_type', 'discount_value', 'scope',
        'product_id', 'product_variant_id', 'product_category_id', 'minimum_order_amount',
        'minimum_quantity', 'usage_limit', 'per_customer_limit', 'usage_count', 'priority',
        'stackable', 'publicly_visible', 'status', 'starts_at', 'ends_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'minimum_quantity' => 'decimal:3',
            'priority' => 'integer',
            'stackable' => 'boolean',
            'publicly_visible' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** @return HasMany<PricingPromotionRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PricingPromotionRedemption::class, 'pricing_promotion_id');
    }

    public function isCurrentlyActive(): bool
    {
        $now = now();

        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gte($now))
            && ($this->usage_limit === null || $this->usage_count < $this->usage_limit);
    }
}
