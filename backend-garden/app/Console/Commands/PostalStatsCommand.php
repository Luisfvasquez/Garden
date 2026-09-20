<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Postal\PostalClockInspector;
use App\Services\Postal\PostalClockSnapshot;
use Illuminate\Console\Command;

/**
 * "¿Hay trabajo atascado?" — the first command the runbook tells you to run
 * when the letters stop arriving (docs/runbook.md §1). Spec §17.5 asked for it
 * and it was never written.
 *
 * Read-only and safe at any hour: it prints, it never repairs.
 */
class PostalStatsCommand extends Command
{
    protected $signature = 'postal:stats';

    protected $description = 'Estado del reloj postal: entregas por estado, atascos, tránsito medio y jobs fallidos';

    public function handle(PostalClockInspector $inspector): int
    {
        $snapshot = $inspector->snapshot();

        $this->newLine();
        $this->line('  <options=bold>Reloj postal</> · '.$snapshot->takenAt->toDateTimeString().' UTC');
        $this->newLine();

        $this->components->twoColumnDetail('<options=bold>Estado</>', '<options=bold>Entregas</>');
        foreach ($snapshot->byStatus as $status => $total) {
            $this->components->twoColumnDetail($status, $total === 0 ? '<fg=gray>0</>' : (string) $total);
        }
        $this->components->twoColumnDetail('<options=bold>total</>', (string) $snapshot->totalDeliveries());

        $this->newLine();
        $this->line('  <options=bold>Reloj</>');
        $this->components->twoColumnDetail(
            'Vencidas en queued',
            $this->flag($snapshot->overdueQueued, sprintf(
                '%d (%d por encima de los %d min de gracia)',
                $snapshot->overdueQueued,
                $snapshot->stuckQueued,
                $snapshot->overdueGraceMinutes,
            ), $snapshot->stuckQueued > 0),
        );
        $this->components->twoColumnDetail(
            'La más vieja en queued',
            $snapshot->oldestQueuedScheduledFor === null
                ? '<fg=gray>ninguna</>'
                : sprintf(
                    '%s UTC (%s de retraso)',
                    $snapshot->oldestQueuedScheduledFor->toDateTimeString(),
                    $this->duration($snapshot->oldestQueuedAgeMinutes()),
                ),
        );
        $this->components->twoColumnDetail(
            'Último despacho',
            $snapshot->lastDispatchedAt === null
                ? '<fg=yellow>nunca</>'
                : sprintf(
                    '%s UTC (hace %s)',
                    $snapshot->lastDispatchedAt->toDateTimeString(),
                    $this->duration($snapshot->minutesSinceLastDispatch()),
                ),
        );
        $this->components->twoColumnDetail(
            'In transit ya vencidas',
            $this->flag($snapshot->overdueInTransit, (string) $snapshot->overdueInTransit, $snapshot->overdueInTransit > 0),
        );
        $this->components->twoColumnDetail(
            'Reservadas y huérfanas',
            $this->flag($snapshot->orphanedBatchRows, sprintf(
                '%d (con dispatch_batch_id y >%d min en queued)',
                $snapshot->orphanedBatchRows,
                $snapshot->orphanedBatchMinutes,
            ), $snapshot->orphanedBatchRows > 0),
        );

        $this->newLine();
        $this->line('  <options=bold>Tránsito y colas</>');
        $this->components->twoColumnDetail(
            'Tránsito medio real',
            $snapshot->averageTransitMinutes === null
                ? '<fg=gray>sin entregas recientes</>'
                : $this->duration((int) round($snapshot->averageTransitMinutes)),
        );
        $this->components->twoColumnDetail(
            'Muestra',
            $snapshot->transitSampleSize === 0
                ? '<fg=gray>0 entregas</>'
                : sprintf('%d entregas · %d días', $snapshot->transitSampleSize, (int) config('postal.health.transit_sample_days')),
        );
        $this->components->twoColumnDetail(
            'failed_jobs pendientes',
            $this->flag($snapshot->failedJobs, (string) $snapshot->failedJobs, $snapshot->failedJobs > 0),
        );

        $this->newLine();
        $this->summary($snapshot);
        $this->newLine();

        return self::SUCCESS;
    }

    private function summary(PostalClockSnapshot $snapshot): void
    {
        if ($snapshot->isHealthy()) {
            $this->components->info('El reloj se mueve. Sin atascos.');

            return;
        }

        foreach ($snapshot->problems() as $problem) {
            $this->components->error($problem);
        }

        $this->line('  Diagnóstico paso a paso: <options=bold>docs/runbook.md §1</>');
    }

    private function flag(int $value, string $text, bool $bad): string
    {
        if ($bad) {
            return "<fg=red;options=bold>{$text}</>";
        }

        return $value === 0 ? "<fg=gray>{$text}</>" : $text;
    }

    private function duration(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        if ($minutes < 0) {
            return 'aún no vencida';
        }

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        if ($minutes < 1440) {
            return sprintf('%dh %02dm', intdiv($minutes, 60), $minutes % 60);
        }

        return sprintf('%dd %dh', intdiv($minutes, 1440), intdiv($minutes % 1440, 60));
    }
}
