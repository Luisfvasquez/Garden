<?php

declare(strict_types=1);

namespace App\Jobs\Postal;

use App\Services\Postal\RandomRecipientPool;
use App\Services\Postal\RandomRecipientQuery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Every 15 minutes: rebuild the Redis SET of users eligible to receive a
 * random letter (docs/api/botella-al-mar.md). Sender-specific gates (blocks,
 * caps, cooldown) are checked by the picker, not here.
 */
class RefreshRandomRecipientPoolJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(RandomRecipientQuery $query, RandomRecipientPool $pool): void
    {
        $ids = $query->baseEligible()->pluck('id')->all();

        /** @var list<string> $ids */
        $pool->replace($ids);
    }
}
