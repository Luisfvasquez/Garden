<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The postal clock stopped. Sent on demand to `OPS_ALERT_EMAIL` — there is no
 * in-app "ops" user — on the `critical` queue, alongside the moderation
 * escalation (backend-garden/docs/jobs-y-colas.md, docs/runbook.md §1).
 *
 * Queued like everything else, with the obvious caveat: if the worker is the
 * thing that died, this email waits with it. That is what the external uptime
 * check of 5A is for; the exit code is the signal that never depends on a queue.
 */
class PostalClockAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $problems
     * @param  array<string, string>  $context
     */
    public function __construct(
        public readonly array $problems,
        public readonly array $context,
    ) {
        $this->onQueue('critical');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[Evergarden · crítico] El reloj postal está parado')
            ->greeting('Las cartas han dejado de moverse')
            ->line('`postal:health` ha fallado. Mientras dure, las cartas programadas no llegan a su destinatario.');

        foreach ($this->problems as $problem) {
            $mail->line('· '.$problem);
        }

        $mail->line('---');

        foreach ($this->context as $label => $value) {
            $mail->line("{$label}: {$value}");
        }

        return $mail
            ->line('Diagnóstico paso a paso: docs/runbook.md §1 («Las cartas no llegan»).')
            ->line('Empieza por comprobar que `schedule:work` y `queue:work` siguen vivos.');
    }
}
