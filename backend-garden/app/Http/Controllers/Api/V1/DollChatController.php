<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DollChatMessageType;
use App\Enums\DollRequestStatus;
use App\Events\DollChatMessageSent;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DollRequests\StoreDollChatMessageRequest;
use App\Http\Resources\DollChatMessageResource;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Services\Dolls\ContactExchangeGuard;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The Doll chat (docs/api/dolls.md § Chat, ADR-0005).
 *
 * The channel is authorised twice: once in routes/channels.php at subscribe
 * time, and again here on every call. The contract is explicit about this —
 * a subscription is a snapshot, and a request can be cancelled or completed
 * between the WebSocket handshake and the next POST.
 */
class DollChatController extends Controller
{
    public function __construct(private readonly ContactExchangeGuard $guard) {}

    /**
     * GET /api/v1/doll-requests/{id}/messages — ascending, oldest first: a
     * transcript is read forwards, unlike the mailbox.
     */
    public function index(Request $request, DollRequest $dollRequest): AnonymousResourceCollection
    {
        $this->authorize('chat', $dollRequest);

        // Reading the history stays open after the request closes — the
        // transcript is consultable for 90 days (PurgeOldDollChatsJob). Only
        // writing requires an open channel.
        $page = DollChatMessage::query()
            ->where('doll_request_id', $dollRequest->getKey())
            ->with('sender')
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '30')));

        $this->markIncomingRead($request, $page->getCollection()->all());

        return DollChatMessageResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    /**
     * POST /api/v1/doll-requests/{id}/messages
     */
    public function store(StoreDollChatMessageRequest $request, DollRequest $dollRequest): JsonResponse
    {
        $this->authorize('chat', $dollRequest);
        $this->assertChannelOpen($dollRequest);

        $body = (string) $request->validated()['body'];
        $flags = $this->guard->inspect($body);

        $message = new DollChatMessage([
            'type' => DollChatMessageType::Text,
            'body' => $body,
            'pii_flags' => $flags === [] ? null : $flags,
        ]);
        $message->doll_request_id = $dollRequest->getKey();
        $message->sender_id = $request->user()->getKey();
        $message->save();

        broadcast(new DollChatMessageSent($message))->toOthers();

        // Warns both parties and logs — it never blocks the message
        // (backend-garden/docs/moderacion.md § PII).
        if ($flags !== []) {
            $warning = $this->guard->warn($dollRequest, $message, $flags);
            broadcast(new DollChatMessageSent($warning));
        }

        $this->syncTurn($dollRequest, $request->user()->getKey());

        return (new DollChatMessageResource($message->load('sender')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * The chat is only writable while the request is actually being worked on.
     * Anything else is over, and says so with its own code so the client can
     * render a read-only transcript instead of an error.
     */
    private function assertChannelOpen(DollRequest $dollRequest): void
    {
        if (! $dollRequest->status->isOpenChannel()) {
            throw new ApiException(
                'Esta conversación está cerrada.',
                'CHANNEL_CLOSED',
                403,
            );
        }
    }

    /**
     * `in_progress ⇄ awaiting_client` tracks whose turn it is: the Doll asking
     * something parks the request on the client, and the client answering
     * hands it back. Both transitions are guarded in the model, so a message
     * that doesn't change whose turn it is simply doesn't move anything.
     */
    private function syncTurn(DollRequest $dollRequest, string $senderId): void
    {
        $dollRequest->refresh();

        if ($senderId === $dollRequest->doll_id && $dollRequest->status === DollRequestStatus::InProgress) {
            $dollRequest->awaitClient();
        } elseif ($senderId === $dollRequest->client_id && $dollRequest->status === DollRequestStatus::AwaitingClient) {
            $dollRequest->resume();
        }
    }

    /**
     * @param  list<DollChatMessage>  $messages
     */
    private function markIncomingRead(Request $request, array $messages): void
    {
        $me = $request->user()->getKey();

        foreach ($messages as $message) {
            if ($message->sender_id !== null && $message->sender_id !== $me) {
                $message->markRead();
            }
        }
    }
}
