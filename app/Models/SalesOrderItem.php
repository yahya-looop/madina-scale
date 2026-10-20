<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id', 'product_id', 'product_variant_id',
        'product_name', 'variant_name', 'sku', 'quantity', 'unit_price',
        'line_total', 'reservation_id',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
    public function reservation(): BelongsTo { return $this->belongsTo(InventoryReservation::class); }
    public function fulfillmentItems(): HasMany { return $this->hasMany(SalesOrderFulfillmentItem::class, 'sales_order_item_id'); }
}
