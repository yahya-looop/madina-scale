<?php

namespace App\Notifications;

use App\Models\InventoryTransfer;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class InventoryTransferNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly InventoryTransfer $transfer,
        private readonly string $event,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->transfer->loadMissing(['product:id,name,sku', 'variant:id,name,sku', 'sourceLocation:id,name,code', 'destinationLocation:id,name,code', 'dispatcher:id,name']);

        return [
            'type' => 'inventory_transfer',
            'event' => $this->event,
            'transfer_id' => $this->transfer->id,
            'transfer_uuid' => $this->transfer->transfer_uuid,
            'status' => $this->transfer->status,
            'product_name' => $this->transfer->variant?->name ?: $this->transfer->product?->name,
            'sku' => $this->transfer->variant?->sku ?: $this->transfer->product?->sku,
            'quantity' => $this->transfer->quantity_sent,
            'source_location' => $this->transfer->sourceLocation?->name,
            'destination_location' => $this->transfer->destinationLocation?->name,
            'dispatched_by' => $this->transfer->dispatcher?->name,
            'dispatched_at' => $this->transfer->dispatched_at?->toISOString(),
            'reason' => $this->transfer->reason,
        ];
    }
}
