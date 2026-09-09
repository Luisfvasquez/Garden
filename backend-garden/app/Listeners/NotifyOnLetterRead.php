<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\LetterRead;
use App\Notifications\LetterReadNotification;

class NotifyOnLetterRead
{
    public function handle(LetterRead $event): void
    {
        $event->delivery->sender?->notify(new LetterReadNotification($event->delivery));
    }
}
