<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int $location_id
 * @property numeric-string $quantity
 * @property string $purpose
 * @property string|null $purpose_label
 * @property string $status
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property string|null $reason
 * @property CarbonInterface|null $expires_at
 * @property int|null $created_by
 * @property int|null $resolved_by
 * @property CarbonInterface|null $resolved_at
 */
class InventoryReservation extends Model
{
    protected $fillable = [
        'product_id', 'product_variant_id', 'location_id', 'quantity',
        'purpose', 'purpose_label', 'status', 'reference_type', 'reference_id', 'reason',
        'expires_at', 'created_by', 'resolved_by', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'location_id' => 'integer',
            'quantity' => 'decimal:4',
            'expires_at' => 'datetime',
            'resolved_at' => 'datetime',
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
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
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

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
