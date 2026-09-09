<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Immutable audit trail of a delivery's journey; feeds the postal-tracking
 * screen (docs/api/entregas-buzon.md). Not every event maps 1:1 to a status —
 * `sorting_office` and `out_for_delivery` are flavour steps within `in_transit`.
 */
enum DeliveryEventType: string
{
    case Created = 'created';
    case Queued = 'queued';
    case Dispatched = 'dispatched';
    case InTransit = 'in_transit';
    case SortingOffice = 'sorting_office';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case Read = 'read';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
