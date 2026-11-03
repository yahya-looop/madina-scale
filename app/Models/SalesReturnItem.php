<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sales_return_id
 * @property int $sales_order_item_id
 * @property numeric-string $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $line_refund
 */
class SalesReturnItem extends Model
{
    protected $fillable = ['sales_return_id', 'sales_order_item_id', 'quantity', 'unit_price', 'line_refund'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_refund' => 'decimal:2'];
    }

    /** @return BelongsTo<SalesReturn, $this> */
    public function salesReturn(): BelongsTo
    {
        return $this->belongsTo(SalesReturn::class);
    }

    /** @return BelongsTo<SalesOrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }
}
