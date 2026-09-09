<?php

declare(strict_types=1);

namespace App\Services\Postal;

use App\Models\TransitRoute;
use Illuminate\Support\Facades\Cache;

/**
 * Supplies fictional sorting-office names for the tracking timeline.
 */
class PostalRoutes
{
    private const CACHE_KEY = 'postal:offices';

    private const TTL = 3600;

    /**
     * @return list<string>
     */
    public function offices(): array
    {
        /** @var list<string> $offices */
        $offices = Cache::remember(self::CACHE_KEY, self::TTL, static function (): array {
            $all = TransitRoute::query()->pluck('waypoints')->flatten()->unique()->values()->all();

            return $all !== [] ? $all : ['Leiden'];
        });

        return $offices;
    }

    /**
     * A short itinerary of `$count` distinct offices (or as many as exist).
     *
     * @return list<string>
     */
    public function itinerary(int $count = 2): array
    {
        $offices = $this->offices();
        shuffle($offices);

        return array_slice($offices, 0, max(1, min($count, count($offices))));
    }
}
