<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the sender: the letter could not be delivered. Not gated — failures always
 * reach the sender so they can revise and try again.
 */
class LetterFailedNotification extends LetterNotification
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'letter.failed',
            'delivery_id' => $this->delivery->id,
            'letter_id' => $this->delivery->letter_id,
            'reason' => $this->delivery->failure_reason,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMail('No pudimos entregar tu carta')
            ->line('Tu carta no llegó a su destino. Puedes revisarla y volver a enviarla.')
            ->action('Ver mis envíos', $this->url('/envios'));
    }
}
