<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LetterDelivery;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A delivery has left the sorting office (queued → in_transit).
 */
class LetterDispatched
{
    use Dispatchable;

    public function __construct(public readonly LetterDelivery $delivery) {}
}
