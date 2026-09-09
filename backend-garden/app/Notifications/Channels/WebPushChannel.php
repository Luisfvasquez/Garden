<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Models\ScheduledPush;
use App\Models\User;
use App\Services\Push\QuietHours;
use Illuminate\Notifications\Notification;

/**
 * Does not push directly: it drops a row into `scheduled_pushes`, honouring the
 * user's quiet hours. `DispatchDuePushesJob` groups and sends
 * (docs/api/comunidad-notificaciones.md).
 */
class WebPushChannel
{
    public function __construct(private readonly QuietHours $quietHours) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! method_exists($notification, 'toWebPush')) {
            return;
        }

        if ($notifiable->settings?->notify_push !== true) {
            return;
        }

        if (! $notifiable->pushSubscriptions()->exists()) {
            return;
        }

        /** @var array{type: string, title: string, body: string, data?: array<string, mixed>}|null $payload */
        $payload = $notification->toWebPush($notifiable);
        if ($payload === null) {
            return;
        }

        ScheduledPush::create([
            'user_id' => $notifiable->getKey(),
            'type' => $payload['type'],
            'title' => $payload['title'],
            'body' => $payload['body'],
            'data' => $payload['data'] ?? null,
            'deliver_after' => $this->quietHours->nextDeliveryTime($notifiable),
        ]);
    }
}
