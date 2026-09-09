<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Moderation\ContentModerator;
use App\Services\Moderation\Drivers\LocalModerator;
use App\Services\Moderation\PiiScanner;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds the `ContentModerator` interface to the driver named in
 * `config('moderation.driver')`. Application code resolves the interface and
 * never the concrete class (backend-garden/docs/moderacion.md, ADR-0008).
 */
class ModerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ContentModerator::class, function (Application $app): ContentModerator {
            $driver = (string) config('moderation.driver', 'local');

            return match ($driver) {
                'local' => new LocalModerator(
                    $app->make(PiiScanner::class),
                    config('moderation.local'),
                ),
                default => throw new InvalidArgumentException("Unknown moderation driver [{$driver}]."),
            };
        });
    }
}
