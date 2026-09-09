<?php

declare(strict_types=1);

namespace App\Jobs\Maintenance;

use App\Enums\ScheduleStatus;
use App\Enums\ScheduleTriggerType;
use App\Models\LetterSchedule;
use App\Services\Scheduling\OccurrenceGenerator;
use App\Services\Scheduling\OccurrenceMaterializer;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Daily. Materialises the occurrences of every active, date-triggered schedule
 * that fall within the next 90 days and already have a letter assigned
 * (ADR-0002). Idempotent per occurrence — safe to run again any time.
 *
 * Posthumous / inactivity schedules are not touched here; that flow (with its
 * legal notice) lands in Fase 4.
 */
class GenerateUpcomingDeliveriesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(OccurrenceGenerator $generator, OccurrenceMaterializer $materializer): void
    {
        $now = CarbonImmutable::now();
        $horizonDays = (int) config('postal.schedules.materialize_horizon_days', 90);

        LetterSchedule::query()
            ->where('status', ScheduleStatus::Active)
            ->where('trigger_type', ScheduleTriggerType::Date)
            ->with(['user', 'recipient'])
            ->cursor()
            ->each(function (LetterSchedule $schedule) use ($generator, $materializer, $now, $horizonDays): void {
                foreach ($generator->upcoming($schedule, $now, $horizonDays) as $occurrence) {
                    $materializer->materialise($schedule, $occurrence);
                }

                $schedule->forceFill(['last_materialized_at' => now()])->save();

                $this->completeIfFinished($schedule, $generator);
            });
    }

    /**
     * A schedule whose every occurrence has a terminal delivery is done.
     */
    private function completeIfFinished(LetterSchedule $schedule, OccurrenceGenerator $generator): void
    {
        $planned = $generator->all($schedule);
        if ($planned === []) {
            return;
        }

        $lastRunsAt = end($planned)->runsAt;
        if ($lastRunsAt->isFuture()) {
            return;
        }

        $open = $schedule->occurrences()
            ->whereHas('delivery', fn ($q) => $q->whereNotIn('status', ['delivered', 'read', 'cancelled', 'failed', 'blocked']))
            ->exists();

        $materialisedCount = $schedule->occurrences()->whereNotNull('delivery_id')->count();

        if (! $open && $materialisedCount >= count($planned)) {
            $schedule->forceFill(['status' => ScheduleStatus::Completed])->save();
        }
    }
}
