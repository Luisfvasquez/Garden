<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        // Contract: ISO-8601 UTC with a `Z` suffix, no fractional seconds.
        Date::serializeUsing(static fn (\DateTimeInterface $date): string => Carbon::instance($date)->toIso8601ZuluString());

        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        $this->configureRateLimiters();
        $this->configureEmailVerification();

        // Listeners in app/Listeners are auto-discovered by their typed handle().
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

        RateLimiter::for('send-letter', fn (Request $request) => Limit::perDay(30)
            ->by((string) $request->user()?->getAuthIdentifier()));

        // Bottle at sea: the daily cap is enforced in RandomLetterQuota; this is
        // just an abuse ceiling (docs/api/_convenciones.md §Rate limiting).
        RateLimiter::for('send-random', fn (Request $request) => Limit::perDay(10)
            ->by((string) $request->user()?->getAuthIdentifier()));

        RateLimiter::for('report', fn (Request $request) => Limit::perHour(10)
            ->by((string) $request->user()?->getAuthIdentifier()));

        RateLimiter::for('create-post', fn (Request $request) => Limit::perHour(5)
            ->by((string) $request->user()?->getAuthIdentifier()));

        RateLimiter::for('comment', fn (Request $request) => Limit::perHour(30)
            ->by((string) $request->user()?->getAuthIdentifier()));
    }

    /**
     * Point the verification link at the versioned API route with a relative
     * signature (host-independent, so it survives proxies and tests).
     */
    private function configureEmailVerification(): void
    {
        VerifyEmail::createUrlUsing(static function (User $notifiable): string {
            return URL::temporarySignedRoute(
                'api.v1.auth.email.verify',
                Carbon::now()->addMinutes((int) config('auth.verification.expire', 60)),
                [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ],
                absolute: false,
            );
        });
    }
}
