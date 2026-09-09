<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\TransitTier;
use Illuminate\Support\Carbon;

/**
 * Everything the sender chose in `POST /letters/{id}/send`.
 */
final readonly class SendOptions
{
    /**
     * @param  list<string>  $recipientHandles
     */
    public function __construct(
        public array $recipientHandles,
        public TransitTier $tier,
        public ?Carbon $arriveAt,
        public bool $isAnonymous = false,
        public ?Carbon $revealSenderAt = null,
        public bool $allowReadReceipt = true,
    ) {}
}
