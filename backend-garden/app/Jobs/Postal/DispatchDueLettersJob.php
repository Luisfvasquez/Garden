<?php

declare(strict_types=1);

namespace App\Jobs\Postal;

use App\Enums\DeliveryStatus;
use App\Models\LetterDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

/**
 * The dispatch tick. Claims a batch of due deliveries atomically, then fans out
 * one idempotent job per delivery. The batch-reserve pattern avoids the
 * `chunkById`-while-mutating skip (backend-garden/docs/jobs-y-colas.md).
 */
class DispatchDueLettersJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Stop a stuck run from blocking the next tick forever. */
    public int $uniqueFor = 300;

    private const BATCH_LIMIT = 500;

    public function __construct()
    {
        $this->onQueue('postal');
    }

    public function handle(): void
    {
        $batchId = (string) Str::uuid();

        $claimed = LetterDelivery::query()
            ->where('status', DeliveryStatus::Queued)
            ->where('scheduled_for', '<=', now())
            ->whereNull('dispatch_batch_id')
            ->limit(self::BATCH_LIMIT)
            ->update(['dispatch_batch_id' => $batchId]);

        if ($claimed === 0) {
            return;
        }

        LetterDelivery::query()
            ->where('dispatch_batch_id', $batchId)
            ->cursor()
            ->each(fn (LetterDelivery $delivery) => DispatchSingleLetterJob::dispatch($delivery->id));
    }
}
