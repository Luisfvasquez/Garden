<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the sender: your letter reached its destination.
 */
class LetterDeliveredNotification extends LetterNotification
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
            'type' => 'letter.delivered',
            'delivery_id' => $this->delivery->id,
            'letter_id' => $this->delivery->letter_id,
            'recipient' => [
                'display_name' => $this->delivery->recipient?->displayName(),
                'postal_handle' => $this->delivery->recipient?->postal_handle,
            ],
            'delivered_at' => $this->delivery->delivered_at?->toIso8601ZuluString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->delivery->recipient?->displayName() ?? 'tu destinatario';

        return $this->baseMail('Tu carta ha sido entregada')
            ->line("Tu carta para {$name} ya está en su buzón.")
            ->action('Ver el seguimiento', $this->url("/envios/{$this->delivery->id}"));
    }
}
