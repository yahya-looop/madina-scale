<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesReturnItem extends Model
{
    protected $fillable = ['sales_return_id', 'sales_order_item_id', 'quantity', 'unit_price', 'line_refund'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_refund' => 'decimal:2'];
    }

    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
