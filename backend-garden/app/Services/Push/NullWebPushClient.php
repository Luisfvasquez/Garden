<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;

/**
 * Used when no VAPID keys are configured (local dev, CI). Logs and drops.
 */
class NullWebPushClient implements WebPushClient
{
    public function send(PushSubscription $subscription, string $payloadJson): bool
    {
        Log::debug('Web push skipped (no VAPID keys)', [
            'subscription_id' => $subscription->id,
            'payload' => $payloadJson,
        ]);

        return true;
    }
}
