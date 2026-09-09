<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\PushSubscription;

/**
 * Delivers one JSON payload to one subscription. Kept behind an interface so
 * the transport (minishlink/web-push, APNs, FCM) never leaks into the domain,
 * and tests can record sends without network.
 */
interface WebPushClient
{
    /**
     * @return bool true to keep the subscription, false if it is gone (410/404)
     *              and should be pruned
     */
    public function send(PushSubscription $subscription, string $payloadJson): bool;
}
