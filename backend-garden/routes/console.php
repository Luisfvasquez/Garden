<?php

declare(strict_types=1);

use App\Jobs\Postal\DeliverArrivedLettersJob;
use App\Jobs\Postal\DispatchDueLettersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * The postal clock. If either tick stops processing while deliveries are due,
 * letters stop arriving — the single most important alert in the system
 * (backend-garden/docs/jobs-y-colas.md).
 */
Schedule::job(new DispatchDueLettersJob)->everyMinute()->withoutOverlapping();
Schedule::job(new DeliverArrivedLettersJob)->everyMinute()->withoutOverlapping();
