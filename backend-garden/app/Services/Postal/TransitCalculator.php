<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Enums\TransitTier;
use App\Models\LetterDelivery;
use Illuminate\Support\Carbon;

/**
 * The postal clock. Transit time = a value inside the tier's window, scaled by
 * a geographic factor, nudged by random jitter, never below the floor
 * (config/postal.php). The sender only ever sees a deliberately fuzzy estimate
 * — "the surprise is the product" (docs/api/entregas-buzon.md).
 */
class TransitCalculator
{
    /**
     * When a letter must LEAVE the office so it arrives around `$arriveAt`.
     * Null arrival means "send now". Never scheduled in the past.
     */
    public function scheduledFor(?Carbon $arriveAt, TransitTier $tier, bool $crossBorder): Carbon
    {
        if ($arriveAt === null) {
            return Carbon::now();
        }

        $estimate = $this->estimateMinutes($tier, $crossBorder);

        return Carbon::now()->max($arriveAt->copy()->subMinutes($estimate));
    }

    /**
     * Deterministic mid-window estimate, used only to place `scheduled_for`.
     */
    public function estimateMinutes(TransitTier $tier, bool $crossBorder): int
    {
        $window = $tier->window();
        $mid = ($window['min'] + $window['max']) / 2;

        return $this->clamp((int) round($mid * $this->geoFactor($crossBorder)));
    }

    /**
     * A fuzzy arrival shown to the sender at send time, before real dispatch.
     */
    public function provisionalEstimate(Carbon $scheduledFor, TransitTier $tier, bool $crossBorder): Carbon
    {
        $estimate = $this->estimateMinutes($tier, $crossBorder);
        $noised = max(1, (int) round($estimate * (1 + $this->noise((float) config('postal.estimate_jitter')))));

        return $scheduledFor->copy()->addMinutes($noised);
    }

    /**
     * The real transit picked at dispatch, plus the arrival timestamps.
     */
    public function dispatchPlan(TransitTier $tier, bool $crossBorder, ?Carbon $now = null): DispatchPlan
    {
        $now ??= Carbon::now();
        $window = $tier->window();

        $base = random_int($window['min'], $window['max']) * $this->geoFactor($crossBorder);
        $jitter = (float) config('postal.transit_jitter');
        $transit = $this->clamp((int) round($base * (1 + $this->noise($jitter))));

        $deliveredAt = $now->copy()->addMinutes($transit);

        $estimateNoise = $this->noise((float) config('postal.estimate_jitter'));
        $estimatedAt = $now->copy()->addMinutes(
            max(1, (int) round($transit * (1 + $estimateNoise)))
        );

        return new DispatchPlan($transit, $deliveredAt, $estimatedAt);
    }

    public function crossBorderFor(LetterDelivery $delivery): bool
    {
        $from = $delivery->sender?->country_code;
        $to = $delivery->recipient?->country_code;

        return $from !== null && $to !== null && $from !== $to;
    }

    private function geoFactor(bool $crossBorder): float
    {
        return (float) config(
            $crossBorder ? 'postal.geographic_factor.cross_border' : 'postal.geographic_factor.same_country'
        );
    }

    private function noise(float $fraction): float
    {
        if ($fraction <= 0) {
            return 0.0;
        }

        return (random_int(-1000, 1000) / 1000) * $fraction;
    }

    private function clamp(int $minutes): int
    {
        return max((int) config('postal.minimum_transit_minutes'), $minutes);
    }
}
