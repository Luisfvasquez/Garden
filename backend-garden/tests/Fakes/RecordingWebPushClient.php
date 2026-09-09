<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Models\PushSubscription;
use App\Services\Push\WebPushClient;

/**
 * Test double: records every (subscription, payload) pair instead of hitting
 * the network. `$fail` endpoints report the subscription as gone.
 */
class RecordingWebPushClient implements WebPushClient
{
    /**
     * @var list<array{subscription_id: string, payload: array<string, mixed>}>
     */
    public array $sent = [];

    /**
     * @var list<string>
     */
    public array $failEndpoints = [];

    public function send(PushSubscription $subscription, string $payloadJson): bool
    {
        $this->sent[] = [
            'subscription_id' => $subscription->id,
            'payload' => (array) json_decode($payloadJson, true),
        ];

        return ! in_array($subscription->endpoint, $this->failEndpoints, true);
    }
}
