<?php

namespace App\Jobs;

use App\Models\InventoryTransfer;
use App\Models\InventoryTransferNotificationOutbox;
use App\Models\User;
use App\Notifications\InventoryTransferNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessInventoryTransferNotificationOutbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly int $outboxId)
    {
        $this->afterCommit();
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120, 300, 900];
    }

    public function handle(): void
    {
        $outbox = DB::transaction(function (): ?InventoryTransferNotificationOutbox {
            $record = InventoryTransferNotificationOutbox::query()
                ->lockForUpdate()
                ->find($this->outboxId);

            if (! $record || $record->status === 'delivered') {
                return null;
            }

            if ($record->status === 'processing' && $record->updated_at?->gt(now()->subMinutes(5))) {
                return null;
            }

            $record->forceFill([
                'status' => 'processing',
                'attempts' => $record->attempts + 1,
                'last_error' => null,
            ])->save();

            return $record;
        }, 3);

        if (! $outbox) {
            return;
        }

        try {
            $transfer = InventoryTransfer::query()->findOrFail($outbox->inventory_transfer_id);
            $recipients = User::query()
                ->where('status', 'active')
                ->whereIn('id', $outbox->recipient_ids ?? [])
                ->get();

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new InventoryTransferNotification($transfer, $outbox->event));
            }

            $outbox->forceFill([
                'status' => 'delivered',
                'delivered_at' => now(),
                'available_at' => null,
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $outbox->forceFill([
                'status' => 'pending',
                'available_at' => now()->addMinutes(min(60, max(1, $outbox->attempts * 5))),
                'last_error' => mb_substr($exception->getMessage(), 0, 4000),
            ])->save();

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $record = InventoryTransferNotificationOutbox::query()->find($this->outboxId);
        if ($record && $record->status !== 'delivered') {
            $record->forceFill([
                'status' => 'pending',
                'available_at' => now()->addMinutes(5),
                'last_error' => mb_substr($exception?->getMessage() ?? 'Notification job failed.', 0, 4000),
            ])->save();
        }
    }
}
