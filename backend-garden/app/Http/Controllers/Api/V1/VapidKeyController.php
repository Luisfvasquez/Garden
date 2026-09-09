<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VapidKeyController extends Controller
{
    /**
     * GET /api/v1/push/vapid-public-key — public. The browser needs this to
     * create a PushSubscription. `enabled` is false when no keys are configured.
     */
    public function __invoke(): JsonResponse
    {
        $key = (string) config('webpush.vapid.public_key');

        return response()->json(['data' => [
            'public_key' => $key !== '' ? $key : null,
            'enabled' => $key !== '',
        ]]);
    }
}
