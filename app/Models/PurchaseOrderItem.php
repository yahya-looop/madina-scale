<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property numeric-string $quantity
 * @property numeric-string $received_quantity
 * @property numeric-string $accepted_quantity
 * @property numeric-string $unit_cost
 * @property numeric-string $discount_amount
 * @property numeric-string $tax_amount
 * @property numeric-string $line_total
 */
class PurchaseOrderItem extends Model
{
    protected $fillable = [
        'purchase_order_id', 'product_id', 'product_variant_id', 'description',
        'quantity', 'received_quantity', 'accepted_quantity', 'unit_cost',
        'discount_amount', 'tax_amount', 'line_total',
    ];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'received_quantity' => 'decimal:4',
            'accepted_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }
}
