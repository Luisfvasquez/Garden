<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What a yearly schedule anchored on 29 February does in a common year
 * (docs/api/programaciones.md).
 */
enum LeapDayPolicy: string
{
    case Feb28 = 'feb_28';
    case Mar01 = 'mar_01';
}
