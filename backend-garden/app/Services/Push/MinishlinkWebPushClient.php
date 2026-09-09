<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

/**
 * Real Web Push transport (VAPID). Native mobile tokens are ignored here — a
 * separate FCM/APNs client lands with the mobile app in Fase 4.
 */
class MinishlinkWebPushClient implements WebPushClient
{
    public function send(PushSubscription $subscription, string $payloadJson): bool
    {
        if (! $subscription->isWeb() || $subscription->endpoint === null) {
            return true; // not ours to deliver; leave it alone
        }

        $auth = [
            'VAPID' => [
                'subject' => (string) config('webpush.vapid.subject'),
                'publicKey' => (string) config('webpush.vapid.public_key'),
                'privateKey' => (string) config('webpush.vapid.private_key'),
            ],
        ];

        $webPush = new WebPush($auth);
        $webPush->queueNotification(
            WebPushSubscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
            ]),
            $payloadJson,
        );

        foreach ($webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                return ! $report->isSubscriptionExpired();
            }
        }

        return true;
    }
}
