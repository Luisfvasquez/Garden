<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Per-delivery lifecycle (spec §7.1). The state lives on `letter_deliveries`,
 * never on `letters` (ADR-0001).
 */
enum DeliveryStatus: string
{
    case Queued = 'queued';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Read = 'read';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
    case Blocked = 'blocked';

    /**
     * A random letter withheld from a stranger pending human review (self-harm
     * or borderline content). Never enters the dispatch set
     * (docs/api/botella-al-mar.md, backend-garden/docs/moderacion.md).
     */
    case Held = 'held';

    /** Visible in the recipient's mailbox and unread counts. */
    public function isInMailbox(): bool
    {
        return match ($this) {
            self::Delivered, self::Read => true,
            default => false,
        };
    }

    /** Terminal states never transition again. */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Read, self::Cancelled, self::Failed, self::Blocked => true,
            default => false,
        };
    }

    /**
     * What the sender is allowed to see. A `blocked` delivery is reported as
     * `delivered` — the block is never revealed (spec §7.1, ADR-0007).
     */
    public function asSeenBySender(): self
    {
        return $this === self::Blocked ? self::Delivered : $this;
    }
}
