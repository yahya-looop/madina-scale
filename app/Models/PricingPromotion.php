<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PricingPromotion extends Model
{
    protected $fillable = [
        'name', 'code', 'description', 'discount_type', 'discount_value', 'scope',
        'product_id', 'product_variant_id', 'product_category_id', 'minimum_order_amount',
        'minimum_quantity', 'usage_limit', 'per_customer_limit', 'usage_count',
        'stackable', 'publicly_visible', 'status', 'starts_at', 'ends_at', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'minimum_order_amount' => 'decimal:2',
            'minimum_quantity' => 'decimal:3',
            'stackable' => 'boolean',
            'publicly_visible' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function category(): BelongsTo { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
    public function redemptions(): HasMany { return $this->hasMany(PricingPromotionRedemption::class, 'pricing_promotion_id'); }

    public function isCurrentlyActive(): bool
    {
        $now = now();
        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->lte($now))
            && ($this->ends_at === null || $this->ends_at->gte($now))
            && ($this->usage_limit === null || $this->usage_count < $this->usage_limit);
    }
}
