<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int $location_id
 * @property string $movement_type
 * @property numeric-string $quantity_delta
 * @property numeric-string $balance_before
 * @property numeric-string $balance_after
 * @property int|null $created_by
 * @property array<string, mixed>|null $metadata
 */
class StockMovement extends Model
{
    protected $fillable = [
        'transfer_group_id',
        'product_id',
        'product_variant_id',
        'location_id',
        'movement_type',
        'quantity_delta',
        'balance_before',
        'balance_after',
        'reason',
        'reference_type',
        'reference_id',
        'idempotency_key',
        'created_by',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'location_id' => 'integer',
            'quantity_delta' => 'decimal:4',
            'balance_before' => 'decimal:4',
            'balance_after' => 'decimal:4',
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
