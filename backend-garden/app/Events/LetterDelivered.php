<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LetterDelivery;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A letter reached its recipient's mailbox (in_transit → delivered). Not fired
 * for blocked deliveries — those never call `markDelivered()`.
 */
class LetterDelivered
{
    use Dispatchable;

    public function __construct(public readonly LetterDelivery $delivery) {}
}
