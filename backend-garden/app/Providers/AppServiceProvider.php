<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureRateLimiters();
    }

    /**
     * Per-action rate limits (docs/api/_convenciones.md §Rate limiting).
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('api', fn (Request $request) => $request->user()
            ? Limit::perMinute(60)->by($request->user()->getAuthIdentifier())
            : Limit::perMinute(30)->by($request->ip()));

        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
