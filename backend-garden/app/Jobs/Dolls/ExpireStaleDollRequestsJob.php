<?php

declare(strict_types=1);

namespace App\Jobs\Dolls;

use App\Enums\DollRequestStatus;
use App\Models\DollRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Hourly tick: a Doll who never responds to a request within 48h loses it
 * automatically (docs/api/dolls.md). A single mass-update — no need for the
 * batch-reserve dance `DispatchDueLettersJob` uses, since expiring doesn't
 * fan out to per-row work.
 */
class ExpireStaleDollRequestsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        DollRequest::query()
            ->where('status', DollRequestStatus::Pending)
            ->where('expires_at', '<=', now())
            ->update([
                'status' => DollRequestStatus::Expired->value,
                'expires_at' => null,
                'updated_at' => now(),
            ]);
    }
}
