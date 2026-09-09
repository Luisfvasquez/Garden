<?php

declare(strict_types=1);

namespace App\Services\Postal;

use Illuminate\Support\Carbon;

/**
 * The numbers a delivery needs the moment it is dispatched: how long it will
 * spend in transit, when it will really arrive, and the fuzzy figure shown to
 * the sender.
 */
final readonly class DispatchPlan
{
    public function __construct(
        public int $transitMinutes,
        public Carbon $deliveredAt,
        public Carbon $estimatedDeliveryAt,
    ) {}
}
