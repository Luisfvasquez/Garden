<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the sender: the recipient opened your letter. Only fired when both sides
 * allow read receipts (checked before the LetterRead event is dispatched).
 */
class LetterReadNotification extends LetterNotification
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'letter.read',
            'delivery_id' => $this->delivery->id,
            'letter_id' => $this->delivery->letter_id,
            'read_at' => $this->delivery->read_at?->toIso8601ZuluString(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->delivery->recipient?->displayName() ?? 'tu destinatario';

        return $this->baseMail('Han leído tu carta')
            ->line("{$name} ha abierto tu carta.");
    }
}
