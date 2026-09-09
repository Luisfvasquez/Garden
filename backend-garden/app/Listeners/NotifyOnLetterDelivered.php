<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LetterDelivered;
use App\Notifications\LetterArrivedNotification;
use App\Notifications\LetterDeliveredNotification;

/**
 * One arrival, two audiences: the recipient hears "a letter arrived", the
 * sender hears "your letter was delivered".
 */
class NotifyOnLetterDelivered
{
    public function handle(LetterDelivered $event): void
    {
        $delivery = $event->delivery;

        $delivery->recipient?->notify(new LetterArrivedNotification($delivery));
        $delivery->sender?->notify(new LetterDeliveredNotification($delivery));
    }
}
