<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePushSubscriptionRequest;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * POST /api/v1/push-subscriptions — register (or refresh) a device.
     */
    public function store(StorePushSubscriptionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $key = $data['platform'] === 'web'
            ? ['endpoint' => $data['endpoint']]
            : ['device_token' => $data['device_token']];

        $subscription = PushSubscription::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey()] + $key,
            [
                'platform' => $data['platform'],
                'public_key' => $data['public_key'] ?? null,
                'auth_token' => $data['auth_token'] ?? null,
                'device_name' => $data['device_name'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'data' => ['id' => $subscription->id, 'platform' => $subscription->platform],
        ], 201);
    }

    public function destroy(Request $request, string $id): Response
    {
        PushSubscription::query()
            ->where('user_id', $request->user()->getKey())
            ->whereKey($id)
            ->delete();

        return response()->noContent();
    }
}
