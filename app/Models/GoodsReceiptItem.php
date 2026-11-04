<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $goods_receipt_id
 * @property int $purchase_order_item_id
 * @property numeric-string $received_quantity
 * @property numeric-string $accepted_quantity
 * @property numeric-string $rejected_quantity
 * @property int|null $stock_movement_id
 */
class GoodsReceiptItem extends Model
{
    protected $fillable = [
        'goods_receipt_id',
        'purchase_order_item_id',
        'received_quantity',
        'accepted_quantity',
        'rejected_quantity',
        'rejection_reason',
        'stock_movement_id',
    ];

    /** @return BelongsTo<PurchaseOrderItem, $this> */
    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:4',
            'accepted_quantity' => 'decimal:4',
            'rejected_quantity' => 'decimal:4',
        ];
    }
}
