<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransferReceipt extends Model
{
    protected $fillable = [
        'inventory_transfer_id',
        'idempotency_key',
        'payload_hash',
        'quantity_received',
        'recipient_note',
        'received_by',
        'resulting_quantity_received',
        'resulting_status',
    ];

    protected function casts(): array
    {
        return [
            'inventory_transfer_id' => 'integer',
            'received_by' => 'integer',
            'quantity_received' => 'decimal:4',
            'resulting_quantity_received' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<InventoryTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(InventoryTransfer::class, 'inventory_transfer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
