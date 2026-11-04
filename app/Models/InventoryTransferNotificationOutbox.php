<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $inventory_transfer_id
 * @property string $event
 * @property list<int> $recipient_ids
 * @property string $status
 * @property int $attempts
 * @property string|null $last_error
 * @property Carbon|null $available_at
 * @property Carbon|null $delivered_at
 */
class InventoryTransferNotificationOutbox extends Model
{
    protected $table = 'inventory_transfer_notification_outbox';

    protected $fillable = [
        'inventory_transfer_id',
        'event',
        'recipient_ids',
        'status',
        'attempts',
        'available_at',
        'delivered_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'inventory_transfer_id' => 'integer',
            'recipient_ids' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
