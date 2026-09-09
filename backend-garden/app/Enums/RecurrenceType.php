<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a `letter_schedule` repeats (docs/api/programaciones.md).
 * `custom_dates` reads its dates from the schedule's `custom_dates` array;
 * every other type steps off the `anchor_date`.
 */
enum RecurrenceType: string
{
    case Once = 'once';
    case Yearly = 'yearly';
    case Monthly = 'monthly';
    case Weekly = 'weekly';
    case CustomDates = 'custom_dates';

    public function usesAnchorStep(): bool
    {
        return match ($this) {
            self::Yearly, self::Monthly, self::Weekly => true,
            default => false,
        };
    }
}
