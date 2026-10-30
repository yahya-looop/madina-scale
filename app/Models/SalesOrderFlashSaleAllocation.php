<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderFlashSaleAllocation extends Model
{
    protected $fillable = [
        'flash_sale_item_id', 'sales_order_id', 'sales_order_item_id', 'order_item_snapshot', 'quantity',
        'regular_unit_price', 'sale_unit_price', 'status', 'consumed_at', 'released_at',
    ];

    protected function casts(): array
    {
        return [
            'order_item_snapshot' => 'array', 'quantity' => 'integer', 'regular_unit_price' => 'decimal:2',
            'sale_unit_price' => 'decimal:2', 'consumed_at' => 'datetime', 'released_at' => 'datetime',
        ];
    }

    public function flashSaleItem(): BelongsTo { return $this->belongsTo(FlashSaleItem::class); }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id'); }
}
