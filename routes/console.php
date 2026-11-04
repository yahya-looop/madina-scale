<?php

use App\Jobs\ProcessInventoryTransferNotificationOutbox;
use App\Models\InventoryTransferNotificationOutbox;
use App\Services\InventoryReservationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:expire-reservations', function (InventoryReservationService $reservations): void {
    $reservations->expireDue();
    $this->info('Expired inventory reservations reconciled.');
})->purpose('Release stock held by expired inventory reservations');

Artisan::command('inventory:dispatch-transfer-notifications {--limit=100}', function (): void {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $staleBefore = now()->subMinutes(5);
    $ids = InventoryTransferNotificationOutbox::query()
        ->where(function ($query) use ($staleBefore): void {
            $query->where(function ($pending): void {
                $pending->where('status', 'pending')
                    ->where(fn ($due) => $due->whereNull('available_at')->orWhere('available_at', '<=', now()));
            })->orWhere(function ($stale) use ($staleBefore): void {
                $stale->where('status', 'processing')->where('updated_at', '<=', $staleBefore);
            });
        })
        ->orderBy('id')
        ->limit($limit)
        ->pluck('id');

    foreach ($ids as $id) {
        try {
            ProcessInventoryTransferNotificationOutbox::dispatch((int) $id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    $this->info($ids->count().' inventory transfer notification(s) queued.');
})->purpose('Dispatch pending inventory transfer notification outbox records');

Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:dispatch-transfer-notifications')->everyMinute()->withoutOverlapping();
