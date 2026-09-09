<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What fires a schedule (docs/api/programaciones.md).
 *
 * - `date`: the occurrence's own datetime. The only type wired in Fase 2.
 * - `inactivity` / `posthumous`: fire after N months without a session, with
 *   legal notice and double confirmation. Column reserved; flow lands in Fase 4.
 */
enum ScheduleTriggerType: string
{
    case Date = 'date';
    case Inactivity = 'inactivity';
    case Posthumous = 'posthumous';
}
