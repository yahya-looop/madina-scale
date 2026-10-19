<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = [
        'order_number', 'status', 'channel', 'location_id',
        'customer_name', 'customer_email', 'customer_phone',
        'currency', 'subtotal', 'discount_total', 'tax_total', 'grand_total',
        'notes', 'created_by', 'confirmed_at', 'cancelled_at', 'cancellation_reason',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function items(): HasMany { return $this->hasMany(SalesOrderItem::class, 'sales_order_id'); }
    public function statusHistory(): HasMany { return $this->hasMany(SalesOrderStatusHistory::class, 'sales_order_id')->latest('id'); }
}
