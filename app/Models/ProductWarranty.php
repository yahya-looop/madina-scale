<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWarranty extends Model
{
    protected $fillable = [
        'warranty_number', 'sales_order_id', 'sales_order_item_id', 'customer_id',
        'location_id', 'serial_number', 'warranty_start', 'warranty_end',
        'status', 'notes', 'registered_by',
    ];

    protected function casts(): array
    {
        return ['warranty_start' => 'date', 'warranty_end' => 'date'];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /** @return BelongsTo<SalesOrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class, 'sales_order_item_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
