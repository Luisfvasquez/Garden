<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How a Doll charges, if at all (docs/api/dolls.md §Pagos). Stripe Connect is
 * deliberately not implemented yet — `free` avoids the legal/fiscal complexity
 * while the module gets validated (ADR-0012).
 */
enum DollRateType: string
{
    case Free = 'free';
    case PerLetter = 'per_letter';
    case Hourly = 'hourly';
}
