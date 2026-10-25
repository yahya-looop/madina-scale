<?php

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

Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
