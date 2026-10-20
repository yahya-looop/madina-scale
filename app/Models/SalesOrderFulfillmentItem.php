<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderFulfillmentItem extends Model
{
    protected $fillable = ['sales_order_fulfillment_id', 'sales_order_item_id', 'quantity'];
    protected function casts(): array { return ['quantity' => 'decimal:4']; }
    public function fulfillment(): BelongsTo { return $this->belongsTo(SalesOrderFulfillment::class, 'sales_order_fulfillment_id'); }
    public function orderItem(): BelongsTo { return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id'); }
}
