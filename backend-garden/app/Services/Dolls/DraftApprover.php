<?php

declare(strict_types=1);

namespace App\Services\Dolls;

use App\Enums\DollChatMessageType;
use App\Exceptions\ApiException;
use App\Models\DollChatMessage;
use App\Models\DollRequest;
use App\Models\Letter;
use App\Services\Postal\LetterStyleCatalog;
use App\Support\TiptapContent;
use Illuminate\Support\Facades\DB;

/**
 * The client approves one of the Doll's drafts (docs/api/dolls.md § Aprobar y
 * cerrar). Three things happen together, or none of them do:
 *
 *  1. a `letters` row is created with **`author_id` = the client** — the Doll
 *     wrote it, the client owns it. `doll_request_id` keeps the credit trail;
 *  2. the draft message is stamped approved, so a retry can't mint a second letter;
 *  3. the request moves to `completed`, which closes the channel.
 *
 * The letter is created as an ordinary unlocked draft: the client sends it
 * later through the normal flow in docs/api/entregas-buzon.md. Approving does
 * not send anything — nothing in this product skips transit time.
 */
class DraftApprover
{
    public function __construct(private readonly LetterStyleCatalog $styles) {}

    public function approve(DollRequest $request, DollChatMessage $draft): Letter
    {
        if (! $draft->isDraft()) {
            throw new ApiException('Ese mensaje no es un borrador.', 'NOT_A_DRAFT', 422);
        }

        if ($draft->draft_approved_at !== null) {
            throw new ApiException('Ese borrador ya fue aprobado.', 'DRAFT_ALREADY_APPROVED', 409);
        }

        $payload = $draft->draft_payload ?? [];
        $body = TiptapContent::sanitize((array) ($payload['body'] ?? []));
        $plain = TiptapContent::toPlainText($body);

        return DB::transaction(function () use ($request, $draft, $payload, $body, $plain): Letter {
            $letter = new Letter([
                'title' => isset($payload['title']) ? (string) $payload['title'] : null,
                'body' => $body,
                'body_plain' => $plain,
                'word_count' => TiptapContent::wordCount($plain),
                'style' => $this->styles->sanitize((array) ($payload['style'] ?? [])),
            ]);
            $letter->author_id = $request->client_id;
            $letter->doll_request_id = $request->getKey();
            $letter->save();

            $draft->markDraftApproved();

            // in_progress|awaiting_client → completed. Guarded in the model.
            $request->complete();

            return $letter;
        });
    }

    /**
     * Next version number for a draft in this request. Versions are per
     * request and monotonic, so the client can always tell which draft is newer.
     */
    public function nextVersion(DollRequest $request): int
    {
        $latest = DollChatMessage::query()
            ->where('doll_request_id', $request->getKey())
            ->where('type', DollChatMessageType::Draft)
            ->max('draft_version');

        return ((int) $latest) + 1;
    }
}
