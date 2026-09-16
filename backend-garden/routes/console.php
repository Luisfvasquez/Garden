<?php

declare(strict_types=1);

use App\Jobs\Dolls\ExpireStaleDollRequestsJob;
use App\Jobs\Maintenance\DispatchDuePushesJob;
use App\Jobs\Maintenance\GenerateUpcomingDeliveriesJob;
use App\Jobs\Postal\DeliverArrivedLettersJob;
use App\Jobs\Postal\DispatchDueLettersJob;
use App\Jobs\Postal\RefreshRandomRecipientPoolJob;
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

// Recurring letters: materialise the next 90 days of occurrences (ADR-0002).
Schedule::job(new GenerateUpcomingDeliveriesJob)->dailyAt('03:00')->withoutOverlapping();

// "Bottle at sea": keep the eligible-recipient pool warm (docs/api/botella-al-mar.md).
Schedule::job(new RefreshRandomRecipientPoolJob)->everyFifteenMinutes();

// Web Push: flush due rows, grouping siblings; quiet-hours rows join once due.
Schedule::job(new DispatchDuePushesJob)->everyMinute()->withoutOverlapping();

// Auto Memory Dolls: a Doll who never responds loses the request (docs/api/dolls.md).
Schedule::job(new ExpireStaleDollRequestsJob)->hourly()->withoutOverlapping();
