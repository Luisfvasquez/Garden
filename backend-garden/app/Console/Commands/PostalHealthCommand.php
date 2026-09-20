<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Notifications\PostalClockAlertNotification;
use App\Services\Postal\PostalClockInspector;
use App\Services\Postal\PostalClockSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * The most important alert in the system: if the scheduler or the worker die,
 * the letters stop arriving **in silence** and nobody notices for hours
 * (backend-garden/docs/jobs-y-colas.md §Alerta crítica).
 *
 * Exits non-zero when the clock is stopped, so it works equally as a scheduled
 * alert, as a manual check and as the target of an external monitor. The three
 * failure conditions live in PostalClockSnapshot::problems().
 */
class PostalHealthCommand extends Command
{
    protected $signature = 'postal:health {--no-alert : Sólo informa; no envía el correo a OPS_ALERT_EMAIL}';

    protected $description = 'Falla si el reloj postal está parado (entregas vencidas sin despachar, tránsitos vencidos, lotes huérfanos)';

    private const COOLDOWN_KEY = 'postal:health:last-alert';

    public function handle(PostalClockInspector $inspector): int
    {
        $snapshot = $inspector->snapshot();
        $problems = $snapshot->problems();

        if ($problems === []) {
            $this->components->info('Reloj postal correcto.');

            return self::SUCCESS;
        }

        foreach ($problems as $problem) {
            $this->components->error($problem);
        }

        Log::critical('El reloj postal está parado', $this->alertContext($snapshot, $problems));

        if (! $this->option('no-alert')) {
            $this->sendAlert($snapshot, $problems);
        }

        $this->line('  Diagnóstico paso a paso: <options=bold>docs/runbook.md §1</>');

        return self::FAILURE;
    }

    /**
     * Mail is best-effort: a broken mailer must not also break the exit code,
     * or the monitor loses the one signal it had (same policy as
     * CriticalAlertDispatcher).
     *
     * @param  list<string>  $problems
     */
    private function sendAlert(PostalClockSnapshot $snapshot, array $problems): void
    {
        $email = trim((string) config('postal.health.alert_email'));

        if ($email === '') {
            $this->components->warn('OPS_ALERT_EMAIL está vacío: la alerta queda sólo en el log.');

            return;
        }

        if ($this->withinCooldown()) {
            $this->components->warn('Alerta ya enviada recientemente; no se repite el correo (ver postal.health.alert_cooldown_minutes).');

            return;
        }

        try {
            Notification::route('mail', $email)
                ->notify(new PostalClockAlertNotification($problems, $this->alertContext($snapshot, $problems)));

            $this->markAlerted();
            $this->components->warn("Alerta enviada a {$email}.");
        } catch (Throwable $e) {
            Log::error('No se pudo enviar la alerta del reloj postal', ['error' => $e->getMessage()]);
            $this->components->warn('No se pudo enviar el correo de alerta; queda en el log.');
        }
    }

    /**
     * The check runs every five minutes; a stopped clock stays stopped until
     * somebody fixes it. Without this, one incident means hundreds of
     * identical emails and the next real alert gets ignored.
     */
    private function withinCooldown(): bool
    {
        $minutes = (int) config('postal.health.alert_cooldown_minutes');

        return $minutes > 0 && Cache::get(self::COOLDOWN_KEY) !== null;
    }

    private function markAlerted(): void
    {
        $minutes = (int) config('postal.health.alert_cooldown_minutes');

        if ($minutes > 0) {
            Cache::put(self::COOLDOWN_KEY, now()->toIso8601String(), now()->addMinutes($minutes));
        }
    }

    /**
     * @param  list<string>  $problems
     * @return array<string, string>
     */
    private function alertContext(PostalClockSnapshot $snapshot, array $problems): array
    {
        return [
            'Entregas vencidas en queued' => sprintf('%d (%d fuera de gracia)', $snapshot->overdueQueued, $snapshot->stuckQueued),
            'Último despacho' => $snapshot->lastDispatchedAt?->toIso8601String() ?? 'nunca',
            'In transit vencidas' => (string) $snapshot->overdueInTransit,
            'Reservadas y huérfanas' => (string) $snapshot->orphanedBatchRows,
            'failed_jobs' => (string) $snapshot->failedJobs,
            'Problemas' => (string) count($problems),
        ];
    }
}
