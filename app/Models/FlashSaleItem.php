<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function sale(): BelongsTo { return $this->belongsTo(FlashSale::class, 'flash_sale_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }

    public function remainingQuantity(): ?int
    {
        return $this->quantity_limit === null ? null : max(0, $this->quantity_limit - $this->quantity_sold);
    }
}
