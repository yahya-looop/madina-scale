<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int|null $product_id
 * @property int|null $product_variant_id
 * @property int $source_location_id
 * @property int $destination_location_id
 * @property numeric-string $quantity_sent
 * @property numeric-string $quantity_received
 * @property int|null $dispatched_by
 * @property int|null $received_by
 * @property int|null $resolved_by
 * @property Carbon|null $dispatched_at
 * @property Carbon|null $received_at
 * @property Carbon|null $resolved_at
 * @property array<string, mixed>|null $metadata
 * @property-read Product|null $product
 * @property-read ProductVariant|null $variant
 * @property-read Location|null $sourceLocation
 * @property-read Location|null $destinationLocation
 * @property-read User|null $dispatcher
 * @property-read User|null $receiver
 * @property-read User|null $resolver
 */
class InventoryTransfer extends Model
{
    protected $fillable = [
        'transfer_uuid', 'idempotency_key', 'product_id', 'product_variant_id',
        'source_location_id', 'destination_location_id', 'quantity_sent',
        'quantity_received', 'status', 'reference_type', 'reference_id', 'reason',
        'recipient_note', 'resolution_note', 'dispatched_by', 'received_by',
        'resolved_by', 'dispatched_at', 'received_at', 'resolved_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_variant_id' => 'integer',
            'source_location_id' => 'integer',
            'destination_location_id' => 'integer',
            'quantity_sent' => 'decimal:4',
            'quantity_received' => 'decimal:4',
            'dispatched_at' => 'datetime',
            'received_at' => 'datetime',
            'resolved_at' => 'datetime',
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
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    /** @return BelongsTo<User, $this> */
    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
