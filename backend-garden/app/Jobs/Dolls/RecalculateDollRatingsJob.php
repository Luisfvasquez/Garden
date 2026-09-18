<?php

declare(strict_types=1);

namespace App\Jobs\Dolls;

use App\Enums\DollRequestStatus;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Hourly rollup of `doll_profiles.rating_avg` / `rating_count` /
 * `completed_requests_count` from `doll_requests` (docs/api/dolls.md).
 *
 * Recomputed from scratch rather than incremented on each rating: an average
 * kept by increment drifts the moment anything is deleted or corrected, and
 * this table is small enough that a full pass costs nothing. The aggregate is
 * a derived value — `doll_requests` is the truth.
 *
 * A single correlated UPDATE, so profiles never sit half-updated and a Doll's
 * public rating can't be read mid-recalculation.
 */
class RecalculateDollRatingsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(): void
    {
        $completed = DollRequestStatus::Completed->value;

        // Ratings live on doll_requests.client_rating; profiles are keyed by
        // user_id, which is what doll_requests.doll_id points at.
        DB::statement(<<<'SQL'
            UPDATE doll_profiles p
            SET rating_avg = COALESCE(agg.avg_rating, 0),
                rating_count = COALESCE(agg.rated, 0),
                completed_requests_count = COALESCE(agg.completed, 0),
                updated_at = NOW()
            FROM (
                SELECT u.id AS user_id,
                       AVG(r.client_rating) FILTER (WHERE r.rated_at IS NOT NULL) AS avg_rating,
                       COUNT(r.client_rating) FILTER (WHERE r.rated_at IS NOT NULL) AS rated,
                       COUNT(r.id) FILTER (WHERE r.status = ?) AS completed
                FROM users u
                LEFT JOIN doll_requests r ON r.doll_id = u.id
                GROUP BY u.id
            ) agg
            WHERE p.user_id = agg.user_id
        SQL, [$completed]);
    }
}
