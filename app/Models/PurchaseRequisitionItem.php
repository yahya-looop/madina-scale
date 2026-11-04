<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $purchase_requisition_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property numeric-string $quantity
 */
class PurchaseRequisitionItem extends Model
{
    protected $fillable = [
        'purchase_requisition_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'notes',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
