<?php

declare(strict_types=1);

namespace App\Jobs\Dolls;

use App\Enums\DollRequestStatus;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Doll chats are purged 90 days after the request closes — declared in the
 * terms (docs/api/dolls.md § Salvaguardas, spec §8.8 step 9).
 *
 * This deletes the transcript, **not** the request and **not** the letter.
 * The request keeps the audit trail (who, when, rating); the letter belongs to
 * the client and outlives all of this. What goes is the conversation, which is
 * the most intimate part and the part nobody promised to keep.
 *
 * Only closed requests are eligible: an open conversation is never touched,
 * however old it is.
 */
class PurgeOldDollChatsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $cutoff = now()->subDays((int) config('dolls.chat_retention_days', 90));

        // "Closed" = it reached a terminal state, and we date it by whichever
        // terminal timestamp was actually set.
        $closedRequestIds = DollRequest::query()
            ->whereIn('status', array_map(
                fn (DollRequestStatus $case) => $case->value,
                array_filter(DollRequestStatus::cases(), fn (DollRequestStatus $c) => $c->isTerminal()),
            ))
            ->whereRaw(
                'COALESCE(completed_at, cancelled_at, rejected_at, updated_at) <= ?',
                [$cutoff],
            )
            ->pluck('id');

        if ($closedRequestIds->isEmpty()) {
            return;
        }

        $deleted = DollChatMessage::query()
            ->whereIn('doll_request_id', $closedRequestIds)
            ->delete();

        if ($deleted > 0) {
            Log::info('Doll chats purged', [
                'messages' => $deleted,
                'requests' => $closedRequestIds->count(),
                'cutoff' => $cutoff->toIso8601ZuluString(),
            ]);
        }
    }
}
