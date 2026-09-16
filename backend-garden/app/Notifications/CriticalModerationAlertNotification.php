<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The real escalation for `minor_safety` / `self_harm` at `critical` severity:
 * an email to the team, outside the normal moderation queue
 * (backend-garden/docs/moderacion.md, docs/api/_convenciones.md).
 *
 * Sent as an on-demand notification to `config('moderation.critical_alert_email')`
 * — there is no in-app "moderation team" user to notify.
 */
class CriticalModerationAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, string>  $context
     */
    public function __construct(
        public readonly string $subject,
        public readonly string $summary,
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
            ->subject('[Evergarden · crítico] '.$this->subject)
            ->greeting('Alerta de moderación crítica')
            ->line($this->summary);

        foreach ($this->context as $label => $value) {
            $mail->line("{$label}: {$value}");
        }

        return $mail->line('Este aviso salta la cola normal de moderación — revisar cuanto antes.');
    }
}
