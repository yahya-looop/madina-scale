<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    protected $fillable = [
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
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

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
            (string) $this->low_stock_threshold,
            '0',
            4
        ) > 0
            && bccomp(
                $this->available_quantity,
                (string) $this->low_stock_threshold,
                4
            ) <= 0;
    }
}

