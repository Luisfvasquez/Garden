<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Models\User;

/**
 * Picks a stranger for a "bottle at sea" at dispatch time. `SRANDMEMBER` from
 * the Redis pool, then a point re-check against the DB — the pool is up to
 * 15 minutes stale (docs/api/botella-al-mar.md). Gives up after N misses so a
 * near-empty pool fails fast instead of hammering the database.
 */
class RandomRecipientPicker
{
    public function __construct(
        private readonly RandomRecipientPool $pool,
        private readonly RandomRecipientQuery $query,
    ) {}

    public function pick(User $sender): ?User
    {
        $attempts = (int) config('postal.random.pick_attempts', 8);
        $seen = [];

        for ($i = 0; $i < $attempts; $i++) {
            $id = $this->pool->random();
            if ($id === null) {
                return null; // pool empty
            }

            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;

            $candidate = User::find($id);
            if ($candidate !== null && $this->query->isEligible($candidate, $sender)) {
                return $candidate;
            }

            // Stale entry — drop it so we do not keep drawing it.
            $this->pool->forget($id);
        }

        return null;
    }
}
