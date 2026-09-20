<?php

declare(strict_types=1);

namespace App\Services\Postal;

use Carbon\CarbonImmutable;

/**
 * A read-only picture of the postal clock at one instant: what `postal:stats`
 * prints and what `postal:health` judges. Pure — every threshold it needs is
 * passed in, so the verdict can be tested without touching the clock itself
 * (backend-garden/docs/jobs-y-colas.md §Alerta crítica, docs/runbook.md §1).
 */
final class PostalClockSnapshot
{
    /**
     * @param  array<string, int>  $byStatus  Every DeliveryStatus, including the zeroes.
     * @param  int  $overdueQueued  `queued` with `scheduled_for` already past, however recently.
     * @param  int  $stuckQueued  …and past by more than the grace window, so the tick really did miss it.
     */
    public function __construct(
        public readonly CarbonImmutable $takenAt,
        public readonly array $byStatus,
        public readonly int $overdueQueued,
        public readonly int $stuckQueued,
        public readonly ?CarbonImmutable $oldestQueuedScheduledFor,
        public readonly ?CarbonImmutable $lastDispatchedAt,
        public readonly int $orphanedBatchRows,
        public readonly int $overdueInTransit,
        public readonly ?float $averageTransitMinutes,
        public readonly int $transitSampleSize,
        public readonly int $failedJobs,
        public readonly int $dispatchSilenceMinutes,
        public readonly int $overdueGraceMinutes,
        public readonly int $orphanedBatchMinutes,
    ) {}

    public function totalDeliveries(): int
    {
        return array_sum($this->byStatus);
    }

    /** How long the oldest undelivered letter has been waiting past its date. */
    public function oldestQueuedAgeMinutes(): ?int
    {
        if ($this->oldestQueuedScheduledFor === null) {
            return null;
        }

        return (int) $this->oldestQueuedScheduledFor->diffInMinutes($this->takenAt, absolute: false);
    }

    public function minutesSinceLastDispatch(): ?int
    {
        if ($this->lastDispatchedAt === null) {
            return null;
        }

        return (int) $this->lastDispatchedAt->diffInMinutes($this->takenAt, absolute: false);
    }

    /**
     * Nothing has left the sorting office recently. On its own this is normal
     * on a quiet system — it only means anything paired with overdue work.
     */
    public function dispatchIsSilent(): bool
    {
        $since = $this->minutesSinceLastDispatch();

        return $since === null || $since >= $this->dispatchSilenceMinutes;
    }

    /**
     * The three ways the clock breaks. Each is independent: orphaned rows are
     * invisible to a dispatcher that is otherwise working perfectly.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $problems = [];

        if ($this->stuckQueued > 0 && $this->dispatchIsSilent()) {
            $problems[] = sprintf(
                '%d entrega(s) en `queued` con `scheduled_for` vencido hace más de %d min, y %s. El reloj postal está parado: las cartas no salen.',
                $this->stuckQueued,
                $this->overdueGraceMinutes,
                $this->lastDispatchedAt === null
                    ? 'no consta ningún despacho'
                    : sprintf('ninguna despachada en los últimos %d min', $this->dispatchSilenceMinutes),
            );
        }

        if ($this->overdueInTransit > 0) {
            $problems[] = sprintf(
                '%d entrega(s) `in_transit` pasaron su `delivered_at` hace más de %d min y siguen sin entregarse. DeliverArrivedLettersJob no las está moviendo.',
                $this->overdueInTransit,
                $this->overdueGraceMinutes,
            );
        }

        if ($this->orphanedBatchRows > 0) {
            $problems[] = sprintf(
                '%d entrega(s) llevan `dispatch_batch_id` asignado y más de %d min en `queued`. El despachador filtra por `dispatch_batch_id IS NULL`, así que nunca las volverá a mirar (runbook §1, causa 2).',
                $this->orphanedBatchRows,
                $this->orphanedBatchMinutes,
            );
        }

        return $problems;
    }

    public function isHealthy(): bool
    {
        return $this->problems() === [];
    }
}
