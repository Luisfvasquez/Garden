<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\LetterDelivery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for the letter lifecycle notifications. Channels: `database`, plus
 * `mail` when the user opted into email. `isEnabled()` lets a subclass honour
 * a per-type preference and stay silent entirely. Every mail carries a
 * preferences link and `List-Unsubscribe` (docs/api/comunidad-notificaciones.md).
 */
abstract class LetterNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly LetterDelivery $delivery)
    {
        $this->onQueue('notifications');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if ($notifiable instanceof User && ! $this->isEnabled($notifiable)) {
            return [];
        }

        $channels = ['database'];

        if ($notifiable instanceof User && $notifiable->settings?->notify_email === true) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /** Per-type opt-out on top of the global `notify_email` switch. */
    protected function isEnabled(User $notifiable): bool
    {
        return true;
    }

    protected function baseMail(string $subject): MailMessage
    {
        return (new MailMessage)
            ->subject($subject)
            ->withSymfonyMessage(function ($message): void {
                $message->getHeaders()->addTextHeader('List-Unsubscribe', '<'.$this->url('/ajustes/notificaciones').'>');
            });
    }

    protected function url(string $path): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base.$path;
    }
}
