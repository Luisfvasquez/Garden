<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the sender: your letter left the office and is on its way.
 */
class LetterDispatchedNotification extends LetterNotification
{
    protected function isEnabled(User $notifiable): bool
    {
        return $notifiable->settings?->notify_on_dispatch_confirm === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'letter.dispatched',
            'delivery_id' => $this->delivery->id,
            'letter_id' => $this->delivery->letter_id,
            'estimated_delivery_at' => $this->delivery->estimated_delivery_at?->toIso8601ZuluString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->baseMail('Tu carta va de camino')
            ->line('El cartero ha recogido tu carta. Llegará en unas horas.')
            ->action('Ver el seguimiento', $this->url("/envios/{$this->delivery->id}"));
    }
}
