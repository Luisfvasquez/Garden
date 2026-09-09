<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LetterDispatched;
use App\Notifications\LetterDispatchedNotification;

class NotifyOnLetterDispatched
{
    public function handle(LetterDispatched $event): void
    {
        $event->delivery->sender?->notify(new LetterDispatchedNotification($event->delivery));
    }
}
