<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $supplier_return_id
 * @property int $purchase_order_item_id
 * @property numeric-string $quantity
 * @property numeric-string $unit_cost
 * @property numeric-string $line_total
 */
class SupplierReturnItem extends Model
{
    protected $fillable = [
        'supplier_return_id',
        'purchase_order_item_id',
        'quantity',
        'unit_cost',
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
