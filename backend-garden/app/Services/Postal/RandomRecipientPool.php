<?php

declare(strict_types=1);

namespace App\Services\Postal;

/**
 * The set of user IDs currently eligible to receive a "bottle at sea".
 * `inRandomOrder()` does not scale, so selection is `SRANDMEMBER` against this
 * pool plus a point re-check in the DB (docs/api/botella-al-mar.md).
 *
 * The pool is a hint, refreshed every 15 minutes; the picker always verifies.
 */
interface RandomRecipientPool
{
    /**
     * Replace the whole pool atomically.
     *
     * @param  list<string>  $userIds
     */
    public function replace(array $userIds): void;

    /** A random member, or null if the pool is empty. */
    public function random(): ?string;

    /** Drop one member (it failed the point-check). */
    public function forget(string $userId): void;

    public function count(): int;

    /**
     * @return list<string>
     */
    public function all(): array;
}
