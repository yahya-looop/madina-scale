<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingPromotionRedemption extends Model
{
    protected $fillable = [
        'pricing_promotion_id', 'sales_order_id', 'customer_id', 'promotion_code',
        'discount_amount', 'status', 'redeemed_at', 'released_at',
    ];

    protected function casts(): array
    {
        return ['discount_amount' => 'decimal:2', 'redeemed_at' => 'datetime', 'released_at' => 'datetime'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(PricingPromotion::class, 'pricing_promotion_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
