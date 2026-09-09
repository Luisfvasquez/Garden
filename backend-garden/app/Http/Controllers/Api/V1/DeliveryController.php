<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\TrackingEventResource;
use App\Models\LetterDelivery;
use App\Support\CursorPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The sender's side: outbox, postal tracking and cancellation.
 */
class DeliveryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LetterDelivery::query()
            ->forSender($request->user()->getKey())
            ->with('recipient')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $this->applyStatusFilter($query, $request->query('status'));

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return DeliveryResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    public function show(LetterDelivery $delivery): DeliveryResource
    {
        $this->authorize('viewAsSender', $delivery);

        return new DeliveryResource($delivery->load('recipient'));
    }

    public function tracking(LetterDelivery $delivery): JsonResponse
    {
        $this->authorize('viewAsSender', $delivery);

        return response()->json([
            'data' => TrackingEventResource::collection($delivery->events)->resolve(),
        ]);
    }

    public function cancel(LetterDelivery $delivery): DeliveryResource
    {
        $this->authorize('cancel', $delivery);

        $delivery->cancel(); // throws GRACE_PERIOD_EXPIRED / INVALID_STATE_TRANSITION (409)

        return new DeliveryResource($delivery->load('recipient'));
    }

    /**
     * @param  Builder<LetterDelivery>  $query
     */
    private function applyStatusFilter(Builder $query, mixed $status): void
    {
        if (! is_string($status) || $status === '') {
            return;
        }

        // The sender sees a blocked delivery as "delivered" (ADR-0007).
        if ($status === DeliveryStatus::Delivered->value) {
            $query->whereIn('status', [DeliveryStatus::Delivered, DeliveryStatus::Blocked]);

            return;
        }

        if (DeliveryStatus::tryFrom($status) !== null) {
            $query->where('status', $status);
        }
    }
}
