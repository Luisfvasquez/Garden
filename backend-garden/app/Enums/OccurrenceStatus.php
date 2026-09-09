<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The state of a single occurrence in the timeline
 * (`GET /schedules/{id}/occurrences`, docs/api/programaciones.md).
 *
 * `empty` and `pending` are virtual (no delivery row yet); the rest mirror the
 * materialised `letter_delivery`, translated to what the sender may see.
 */
enum OccurrenceStatus: string
{
    case Empty = 'empty';       // no letter assigned
    case Pending = 'pending';   // letter assigned, not yet materialised
    case Queued = 'queued';
    case InTransit = 'in_transit';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public static function fromDelivery(DeliveryStatus $status): self
    {
        return match ($status->asSeenBySender()) {
            DeliveryStatus::Queued => self::Queued,
            DeliveryStatus::InTransit => self::InTransit,
            DeliveryStatus::Delivered, DeliveryStatus::Read => self::Delivered,
            DeliveryStatus::Cancelled, DeliveryStatus::Failed => self::Cancelled,
            default => self::Queued,
        };
    }
}
