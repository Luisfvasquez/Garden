<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Postal\RandomRecipientPool;
use App\Services\Postal\RedisRandomRecipientPool;
use Illuminate\Support\ServiceProvider;

class PostalServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // The "bottle at sea" eligible-recipient pool. Redis in every real
        // environment; tests bind an in-memory double.
        $this->app->singleton(RandomRecipientPool::class, RedisRandomRecipientPool::class);
    }
}
