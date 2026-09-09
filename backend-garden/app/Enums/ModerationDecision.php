<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The outcome of a single `ContentModerator::check()` call
 * (backend-garden/docs/moderacion.md).
 *
 * - `Approved`: publish / enqueue normally.
 * - `Flagged`: hold, invisible, until a human reviews (`moderation_status = flagged`).
 * - `Rejected`: hard block; the author is told with `CONTENT_FLAGGED`.
 */
enum ModerationDecision: string
{
    case Approved = 'approved';
    case Flagged = 'flagged';
    case Rejected = 'rejected';

    /** Anything that must not reach its audience without a human in the loop. */
    public function blocks(): bool
    {
        return $this !== self::Approved;
    }

    public function toModerationStatus(): ModerationStatus
    {
        return match ($this) {
            self::Approved => ModerationStatus::Approved,
            self::Flagged => ModerationStatus::Flagged,
            self::Rejected => ModerationStatus::Rejected,
        };
    }
}
