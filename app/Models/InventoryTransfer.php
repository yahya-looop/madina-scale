<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'source_location_id');
    }

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function dispatcher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
