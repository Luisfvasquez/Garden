<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TransitTier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\SendLetterRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Letter;
use App\Services\Postal\LetterSender;
use App\Services\Postal\SendOptions;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class SendLetterController extends Controller
{
    /**
     * POST /api/v1/letters/{letter}/send — one queued delivery per recipient.
     */
    public function __invoke(SendLetterRequest $request, Letter $letter, LetterSender $sender): JsonResponse
    {
        $this->authorize('send', $letter);

        $data = $request->validated();
        $delivery = $data['delivery'];

        $options = new SendOptions(
            recipientHandles: array_map(
                static fn (array $r): string => $r['postal_handle'],
                $data['recipients'],
            ),
            tier: TransitTier::from($delivery['tier']),
            arriveAt: isset($delivery['arrive_at']) ? Carbon::parse($delivery['arrive_at']) : null,
            isAnonymous: (bool) ($data['is_anonymous'] ?? false),
            revealSenderAt: isset($data['reveal_sender_at']) ? Carbon::parse($data['reveal_sender_at']) : null,
            allowReadReceipt: (bool) ($data['allow_read_receipt'] ?? true),
        );

        $deliveries = $sender->send($letter, $request->user(), $options);

        return DeliveryResource::collection($deliveries)
            ->response()
            ->setStatusCode(201);
    }
}
