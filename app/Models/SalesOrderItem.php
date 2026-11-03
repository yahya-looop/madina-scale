<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $sales_order_id
 * @property int $product_id
 * @property int $product_variant_id
 * @property int|null $reservation_id
 * @property numeric-string $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $line_total
 * @property array<string, mixed>|null $pricing_snapshot
 * @property-read ProductVariant|null $variant
 */
class SalesOrderItem extends Model
{
    protected $fillable = [
        'sales_order_id', 'product_id', 'product_variant_id',
        'product_name', 'variant_name', 'sku', 'quantity', 'unit_price',
        'line_total', 'reservation_id', 'pricing_snapshot',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2', 'pricing_snapshot' => 'array'];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

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

    /** @return BelongsTo<InventoryReservation, $this> */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(InventoryReservation::class);
    }

    /** @return HasMany<ProductWarranty, $this> */
    public function warranties(): HasMany
    {
        return $this->hasMany(ProductWarranty::class, 'sales_order_item_id');
    }

    /** @return HasMany<SalesOrderFulfillmentItem, $this> */
    public function fulfillmentItems(): HasMany
    {
        return $this->hasMany(SalesOrderFulfillmentItem::class, 'sales_order_item_id');
    }
}
