<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\LetterDelivery;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * The recipient opened a letter. Fired only when the recipient allows read
 * receipts; a listener notifies the sender (wired with the notifications work).
 */
class LetterRead
{
    use Dispatchable;

    public function __construct(public readonly LetterDelivery $delivery) {}
}
