<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Push\MinishlinkWebPushClient;
use App\Services\Push\NullWebPushClient;
use App\Services\Push\WebPushClient;
use Illuminate\Support\ServiceProvider;

class PushServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // No VAPID keys → a client that logs and drops (local dev, CI).
        $this->app->singleton(WebPushClient::class, function (): WebPushClient {
            return config('webpush.vapid.public_key') && config('webpush.vapid.private_key')
                ? new MinishlinkWebPushClient
                : new NullWebPushClient;
        });
    }
}
