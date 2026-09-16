<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * State machine for `doll_requests` (docs/api/dolls.md):
 *
 *   pending --accept--> accepted --start--> in_progress <-> awaiting_client
 *      |                                          |
 *      | reject                                   | complete
 *      v                                          v
 *   rejected                                  completed
 *      |
 *      | no response in 48h
 *      v
 *   expired
 *
 * `cancelled` reaches from any non-terminal state.
 */
enum DollRequestStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case InProgress = 'in_progress';
    case AwaitingClient = 'awaiting_client';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** The chat channel only exists while the request is being worked on (docs/api/dolls.md). */
    public function isOpenChannel(): bool
    {
        return match ($this) {
            self::InProgress, self::AwaitingClient => true,
            default => false,
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Rejected, self::Expired, self::Cancelled => true,
            default => false,
        };
    }
}
