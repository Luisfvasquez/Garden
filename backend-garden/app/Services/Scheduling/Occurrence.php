<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use Carbon\CarbonImmutable;

/**
 * One planned occurrence of a schedule. Identified by its local calendar date;
 * `runsAt` is the UTC instant the letter should arrive (the target the transit
 * calculator works backwards from).
 */
final readonly class Occurrence
{
    public function __construct(
        public int $index,
        public string $date,        // local "Y-m-d"
        public string $localTime,   // "HH:MM"
        public CarbonImmutable $runsAt, // UTC
    ) {}
}
