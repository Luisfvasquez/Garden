<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DollChatMessageType;
use App\Events\DollChatMessageSent;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DollRequests\StoreDollDraftRequest;
use App\Http\Resources\DollChatMessageResource;
use App\Http\Resources\LetterResource;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Services\Dolls\DraftApprover;
use App\Support\TiptapContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Versioned drafts inside a Doll chat (docs/api/dolls.md § POST /drafts and
 * § Aprobar y cerrar).
 *
 * A draft is just a chat message of type `draft` carrying `draft_payload` —
 * it lives in the transcript, in order, next to the conversation that produced
 * it (spec §8.8 step 6).
 */
class DollDraftController extends Controller
{
    public function __construct(private readonly DraftApprover $approver) {}

    /**
     * POST /api/v1/doll-requests/{id}/drafts — the Doll shares a new version.
     */
    public function store(StoreDollDraftRequest $request, DollRequest $dollRequest): JsonResponse
    {
        $this->authorize('sendDraft', $dollRequest);
        $this->assertChannelOpen($dollRequest);

        // Read raw: validated() prunes the nested Tiptap nodes, same as LetterController.
        $rawBody = (array) $request->input('draft_payload.body', []);
        $body = TiptapContent::sanitize($rawBody);

        $max = (int) config('postal.limits.body_max_chars');
        if (mb_strlen(TiptapContent::toPlainText($body)) > $max) {
            throw new ApiException(
                "El borrador supera el máximo de {$max} caracteres.",
                'VALIDATION_ERROR',
                422,
            );
        }

        $draft = new DollChatMessage([
            'type' => DollChatMessageType::Draft,
            'body' => $request->validated()['note'] ?? null,
            'draft_payload' => [
                'title' => $request->input('draft_payload.title'),
                'body' => $body,
                'style' => (array) $request->input('draft_payload.style', []),
            ],
            'draft_version' => $this->approver->nextVersion($dollRequest),
        ]);
        $draft->doll_request_id = $dollRequest->getKey();
        $draft->sender_id = $request->user()->getKey();
        $draft->save();

        broadcast(new DollChatMessageSent($draft))->toOthers();

        return (new DollChatMessageResource($draft->load('sender')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * POST /api/v1/doll-requests/{id}/drafts/{draft}/approve — the client
     * accepts a version. This is what completes the request and mints the
     * letter; there is no standalone `/complete` endpoint (docs/api/dolls.md).
     */
    public function approve(Request $request, DollRequest $dollRequest, DollChatMessage $draft): JsonResponse
    {
        $this->authorize('approveDraft', $dollRequest);
        $this->assertChannelOpen($dollRequest);

        $letter = $this->approver->approve($dollRequest, $draft);

        return (new LetterResource($letter->loadCount('deliveries')))
            ->response()
            ->setStatusCode(201);
    }

    private function assertChannelOpen(DollRequest $dollRequest): void
    {
        if (! $dollRequest->status->isOpenChannel()) {
            throw new ApiException('Esta conversación está cerrada.', 'CHANNEL_CLOSED', 403);
        }
    }
}
