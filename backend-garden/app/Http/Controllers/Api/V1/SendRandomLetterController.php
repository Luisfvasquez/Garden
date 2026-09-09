<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Enums\TransitTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\SendRandomLetterRequest;
use App\Http\Resources\RandomDeliveryResource;
use App\Models\Letter;
use App\Services\Postal\RandomLetterQuota;
use App\Services\Postal\RandomLetterSender;
use Illuminate\Http\JsonResponse;

/**
 * POST /api/v1/letters/{letter}/send-random — a "bottle at sea" (ADR-0004).
 * The stranger is resolved at dispatch; `recipient` is always null here.
 */
class SendRandomLetterController extends Controller
{
    public function __invoke(
        SendRandomLetterRequest $request,
        Letter $letter,
        RandomLetterSender $sender,
        RandomLetterQuota $quota,
    ): JsonResponse {
        $this->authorize('send', $letter);

        $delivery = $sender->send(
            $letter,
            $request->user(),
            TransitTier::from($request->validated('tier')),
        );

        $remaining = $quota->snapshot($request->user())['daily_remaining'];
        $status = $delivery->status === DeliveryStatus::Held ? 202 : 201;

        return (new RandomDeliveryResource($delivery, $remaining))
            ->response()
            ->setStatusCode($status);
    }
}
