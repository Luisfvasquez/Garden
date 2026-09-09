<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DeliveryStatus;
use App\Enums\LetterKind;
use App\Enums\TransitTier;
use App\Events\LetterRead;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Mailbox\ArchiveMailboxRequest;
use App\Http\Requests\Mailbox\FavoriteMailboxRequest;
use App\Http\Requests\Mailbox\ReplyAnonymousRequest;
use App\Http\Resources\LetterResource;
use App\Http\Resources\MailboxEnvelopeResource;
use App\Http\Resources\MailboxLetterResource;
use App\Http\Resources\RandomDeliveryResource;
use App\Models\Letter;
use App\Models\LetterDelivery;
use App\Services\Postal\RandomLetterQuota;
use App\Services\Postal\RandomLetterSender;
use App\Support\CursorPage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The recipient's side. `in_transit` deliveries never appear here, in listings
 * or in counts — the surprise is the product (docs/api/entregas-buzon.md).
 */
class MailboxController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LetterDelivery::query()
            ->inMailbox($request->user()->getKey())
            ->with(['letter' => fn ($q) => $q->withCount('attachments'), 'sender'])
            ->orderByDesc('delivered_at')
            ->orderByDesc('id');

        $this->applyMailboxFilter($query, $request->query('status'));

        if (is_string($request->query('updated_since'))) {
            $query->where('updated_at', '>=', $request->date('updated_since'));
        }

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return MailboxEnvelopeResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    public function show(LetterDelivery $delivery): MailboxEnvelopeResource
    {
        $this->authorize('viewInMailbox', $delivery);

        return new MailboxEnvelopeResource(
            $delivery->load(['letter' => fn ($q) => $q->withCount('attachments'), 'sender'])
        );
    }

    public function open(LetterDelivery $delivery): MailboxLetterResource
    {
        $this->authorize('mutateMailbox', $delivery);

        $wasUnread = $delivery->status === DeliveryStatus::Delivered;
        $delivery->markRead();

        if ($wasUnread && $delivery->allow_read_receipt && $this->recipientSharesReceipts($delivery)) {
            LetterRead::dispatch($delivery);
        }

        return new MailboxLetterResource(
            $delivery->load(['letter.attachments', 'sender'])
        );
    }

    public function archive(ArchiveMailboxRequest $request, LetterDelivery $delivery): MailboxEnvelopeResource
    {
        $this->authorize('mutateMailbox', $delivery);

        $delivery->setArchived($request->boolean('archived', ! $delivery->isArchived()));

        return $this->envelope($delivery);
    }

    public function favorite(FavoriteMailboxRequest $request, LetterDelivery $delivery): MailboxEnvelopeResource
    {
        $this->authorize('mutateMailbox', $delivery);

        $delivery->setFavorite($request->boolean('favorite', ! $delivery->is_favorite));

        return $this->envelope($delivery);
    }

    public function reply(Request $request, LetterDelivery $delivery): JsonResponse
    {
        $this->authorize('viewInMailbox', $delivery);

        $author = $request->user();

        $openDrafts = Letter::query()
            ->where('author_id', $author->getKey())
            ->where('is_locked', false)
            ->count();

        if ($openDrafts >= (int) config('postal.limits.max_open_drafts')) {
            throw new ApiException('Has alcanzado el máximo de borradores abiertos.', 'DRAFT_LIMIT_REACHED', 422);
        }

        $reply = new Letter([
            'body' => ['type' => 'doc', 'content' => []],
            'body_plain' => '',
            'word_count' => 0,
            'kind' => LetterKind::Direct,
            'in_reply_to_delivery_id' => $delivery->id,
        ]);
        $reply->author_id = $author->getKey();
        $reply->save();

        return (new LetterResource($reply->loadCount('deliveries')))->response()->setStatusCode(201);
    }

    /**
     * POST /api/v1/mailbox/{delivery}/reply-anonymous — the one reply the
     * recipient of a bottle at sea may send back (docs/api/botella-al-mar.md).
     */
    public function replyAnonymous(
        ReplyAnonymousRequest $request,
        LetterDelivery $delivery,
        RandomLetterSender $sender,
        RandomLetterQuota $quota,
    ): JsonResponse {
        $this->authorize('mutateMailbox', $delivery);

        $reply = $sender->replyAnonymously(
            $delivery,
            $request->user(),
            (array) $request->input('body'),
            TransitTier::from($request->validated('tier', 'standard')),
        );

        $remaining = $quota->snapshot($request->user())['daily_remaining'];

        return (new RandomDeliveryResource($reply, $remaining))
            ->response()
            ->setStatusCode($reply->status->value === 'held' ? 202 : 201);
    }

    /**
     * POST /api/v1/mailbox/{delivery}/open-correspondence — either party opts in
     * to reveal handles. Both must accept before anything is revealed.
     */
    public function openCorrespondence(Request $request, LetterDelivery $delivery): JsonResponse
    {
        $me = $request->user()->getKey();

        if ($delivery->delivery_mode->value !== 'random'
            || ! in_array($me, [$delivery->sender_id, $delivery->recipient_id], true)) {
            throw new ApiException('No existe.', 'NOT_FOUND', 404);
        }

        $delivery->acceptOpenCorrespondence($me);
        $delivery->refresh();

        return response()->json(['data' => [
            'sender_accepted' => $delivery->open_correspondence_sender_at !== null,
            'recipient_accepted' => $delivery->open_correspondence_recipient_at !== null,
            'opened' => $delivery->correspondenceOpened(),
        ]]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = LetterDelivery::query()
            ->inMailbox($request->user()->getKey())
            ->where('status', DeliveryStatus::Delivered)
            ->whereNull('archived_at')
            ->count();

        return response()->json(['data' => ['count' => $count]]);
    }

    private function envelope(LetterDelivery $delivery): MailboxEnvelopeResource
    {
        return new MailboxEnvelopeResource(
            $delivery->load(['letter' => fn ($q) => $q->withCount('attachments'), 'sender'])
        );
    }

    private function recipientSharesReceipts(LetterDelivery $delivery): bool
    {
        return $delivery->recipient?->settings?->share_read_receipts === true;
    }

    /**
     * @param  Builder<LetterDelivery>  $query
     */
    private function applyMailboxFilter(Builder $query, mixed $status): void
    {
        match ($status) {
            'unread' => $query->where('status', DeliveryStatus::Delivered)->whereNull('archived_at'),
            'read' => $query->where('status', DeliveryStatus::Read)->whereNull('archived_at'),
            'archived' => $query->whereNotNull('archived_at'),
            'favorite' => $query->where('is_favorite', true),
            default => $query->whereNull('archived_at'),
        };
    }
}
