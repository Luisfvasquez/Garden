<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\DeliveryStatus;
use App\Models\LetterDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the state of the postal clock. The only place that knows which
 * queries answer "are the letters moving?", so `postal:stats` and
 * `postal:health` can never disagree about what they are looking at
 * (backend-garden/docs/jobs-y-colas.md, docs/runbook.md §1).
 *
 * Read-only by construction: it never writes, never repairs and never
 * dispatches anything. Releasing orphaned rows stays a deliberate human step.
 */
class PostalClockInspector
{
    public function snapshot(): PostalClockSnapshot
    {
        $now = CarbonImmutable::now();

        $silence = (int) config('postal.health.dispatch_silence_minutes');
        $grace = (int) config('postal.health.overdue_grace_minutes');
        $orphanAfter = (int) config('postal.health.orphaned_batch_minutes');
        $sampleDays = (int) config('postal.health.transit_sample_days');

        [$averageTransit, $transitSample] = $this->transit($now, $sampleDays);

        return new PostalClockSnapshot(
            takenAt: $now,
            byStatus: $this->byStatus(),
            overdueQueued: $this->queued()->where('scheduled_for', '<=', $now)->count(),
            stuckQueued: $this->queued()->where('scheduled_for', '<=', $now->subMinutes($grace))->count(),
            oldestQueuedScheduledFor: $this->toCarbon($this->queued()->min('scheduled_for')),
            lastDispatchedAt: $this->toCarbon(LetterDelivery::query()->max('dispatched_at')),
            orphanedBatchRows: $this->queued()
                ->whereNotNull('dispatch_batch_id')
                ->where('updated_at', '<=', $now->subMinutes($orphanAfter))
                ->count(),
            overdueInTransit: LetterDelivery::query()
                ->where('status', DeliveryStatus::InTransit)
                ->whereNotNull('delivered_at')
                ->where('delivered_at', '<=', $now->subMinutes($grace))
                ->count(),
            averageTransitMinutes: $averageTransit,
            transitSampleSize: $transitSample,
            failedJobs: $this->failedJobs(),
            dispatchSilenceMinutes: $silence,
            overdueGraceMinutes: $grace,
            orphanedBatchMinutes: $orphanAfter,
        );
    }

    /**
     * Counts per status, with the empty ones spelled out — a missing row and a
     * zero mean the same thing to the reader, and the table reads better whole.
     *
     * @return array<string, int>
     */
    private function byStatus(): array
    {
        /** @var array<string, int> $counted */
        $counted = LetterDelivery::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $all = [];
        foreach (DeliveryStatus::cases() as $status) {
            $all[$status->value] = $counted[$status->value] ?? 0;
        }

        return $all;
    }

    /**
     * Measured transit, not the planned `transit_duration_minutes`: what the
     * recipients actually waited. Postgres-only arithmetic (ADR-0006).
     *
     * @return array{0: float|null, 1: int}
     */
    private function transit(CarbonImmutable $now, int $sampleDays): array
    {
        $completed = LetterDelivery::query()
            ->whereIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Read])
            ->whereNotNull('dispatched_at')
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '>=', $now->subDays($sampleDays));

        $size = (clone $completed)->count();

        if ($size === 0) {
            return [null, 0];
        }

        $average = $completed->avg(
            DB::raw('EXTRACT(EPOCH FROM (delivered_at - dispatched_at)) / 60')
        );

        return [$average === null ? null : (float) $average, $size];
    }

    /**
     * @return Builder<LetterDelivery>
     */
    private function queued(): Builder
    {
        return LetterDelivery::query()->where('status', DeliveryStatus::Queued);
    }

    /** Zero rather than a crash when the failed-job table is not the database one. */
    private function failedJobs(): int
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');

        if ($table === '' || ! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->count();
    }

    private function toCarbon(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        return $value instanceof \DateTimeInterface
            ? CarbonImmutable::instance($value)
            : CarbonImmutable::parse((string) $value);
    }
}
