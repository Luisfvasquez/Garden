<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * When a push for this user may actually be delivered. Inside their quiet-hours
 * window (their local time, possibly wrapping midnight) the push is held until
 * the window ends (docs/api/comunidad-notificaciones.md).
 */
class QuietHours
{
    public function nextDeliveryTime(User $user): CarbonImmutable
    {
        $now = CarbonImmutable::now();
        $settings = $user->settings;

        $start = $settings?->quiet_hours_start;
        $end = $settings?->quiet_hours_end;
        if ($start === null || $end === null || $start === $end) {
            return $now;
        }

        $tz = $user->timezone ?: 'UTC';
        $localNow = $now->setTimezone($tz);
        $localStart = $localNow->setTimeFromTimeString($start);
        $localEnd = $localNow->setTimeFromTimeString($end);

        $wraps = $localStart->greaterThan($localEnd);

        $inWindow = $wraps
            ? ($localNow->greaterThanOrEqualTo($localStart) || $localNow->lessThan($localEnd))
            : ($localNow->greaterThanOrEqualTo($localStart) && $localNow->lessThan($localEnd));

        if (! $inWindow) {
            return $now;
        }

        // Deliver at the next occurrence of `end`.
        $release = $localEnd;
        if ($wraps && $localNow->greaterThanOrEqualTo($localStart)) {
            $release = $localEnd->addDay();
        }

        return $release->utc();
    }
}
