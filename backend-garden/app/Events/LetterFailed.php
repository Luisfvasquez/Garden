<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LetterDelivery;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A delivery could not be completed (recipient gone, dispatch error). The
 * sender is told and the letter can be revised and sent again.
 */
class LetterFailed
{
    use Dispatchable;

    public function __construct(public readonly LetterDelivery $delivery) {}
}
