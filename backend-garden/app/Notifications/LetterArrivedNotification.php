<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Support\SenderView;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * To the recipient: a letter reached your mailbox. Never reveals the body, and
 * never the sender when the letter is anonymous.
 */
class LetterArrivedNotification extends LetterNotification
{
    protected function isEnabled(User $notifiable): bool
    {
        return $notifiable->settings?->notify_on_arrival !== false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $sender = SenderView::summary($this->delivery);

        return [
            'type' => 'letter.arrived',
            'delivery_id' => $this->delivery->id,
            'title' => $this->delivery->letter->title,
            'sender' => ['display_name' => $sender['display_name'], 'postal_handle' => $sender['postal_handle']],
            'is_anonymous' => SenderView::isHidden($this->delivery),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = SenderView::summary($this->delivery)['display_name'];

        return $this->baseMail('Ha llegado una carta')
            ->greeting('Tienes correo')
            ->line("{$sender} te ha escrito.")
            ->action('Ir al buzón', $this->url("/buzon/{$this->delivery->id}"))
            ->line('Ábrela cuando tengas un momento tranquilo.');
    }
}
