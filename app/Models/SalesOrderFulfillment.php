<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderFulfillment extends Model
{
    protected $fillable = ['fulfillment_number', 'sales_order_id', 'status', 'notes', 'created_by', 'fulfilled_at'];

    protected function casts(): array
    {
        return ['fulfilled_at' => 'datetime'];
    }

    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(SalesOrderFulfillmentItem::class, 'sales_order_fulfillment_id'); }
}
