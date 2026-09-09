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

    /**
     * @return array{type: string, title: string, body: string, data?: array<string, mixed>}
     */
    public function toWebPush(User $notifiable): array
    {
        // Never the body; never the sender when anonymous.
        $body = SenderView::isHidden($this->delivery)
            ? 'Alguien te ha escrito.'
            : SenderView::summary($this->delivery)['display_name'].' te ha escrito.';

        return [
            'type' => 'letter.arrived',
            'title' => 'Ha llegado una carta',
            'body' => $body,
            'data' => ['url' => "/buzon/{$this->delivery->id}"],
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
