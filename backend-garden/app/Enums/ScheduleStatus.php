<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a `letter_schedule`. Only `active` schedules are materialised by
 * `GenerateUpcomingDeliveriesJob` (docs/api/programaciones.md).
 */
enum ScheduleStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';

    public function isMaterialisable(): bool
    {
        return $this === self::Active;
    }
}
