<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LetterFailed;
use App\Notifications\LetterFailedNotification;

class NotifyOnLetterFailed
{
    public function handle(LetterFailed $event): void
    {
        $event->delivery->sender?->notify(new LetterFailedNotification($event->delivery));
    }
}
