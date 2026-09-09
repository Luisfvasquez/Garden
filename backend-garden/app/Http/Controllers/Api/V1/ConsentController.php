<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConsentStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\RespondConsentRequest;
use App\Http\Resources\PostResource;
use App\Models\PublicPost;
use App\Services\Blog\BlogPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The original author's side of the shared-letter consent handshake
 * (docs/api/blog.md). They see the exact preview of what would be published.
 */
class ConsentController extends Controller
{
    public function __construct(private readonly BlogPublisher $publisher) {}

    /**
     * GET /api/v1/consent-requests — posts waiting on my consent.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = PublicPost::query()
            ->where('consent_status', ConsentStatus::Pending)
            ->whereHas('sourceDelivery', fn ($q) => $q->where('sender_id', $request->user()->getKey()))
            ->with(['author', 'tags', 'reactions'])
            ->orderByDesc('created_at')
            ->get();

        return PostResource::collection($posts);
    }

    /**
     * POST /api/v1/consent-requests/{post}/respond — { granted: bool }.
     */
    public function respond(RespondConsentRequest $request, PublicPost $post): JsonResponse
    {
        $this->assertAddressee($request, $post);

        $this->publisher->respondConsent($post, $request->boolean('granted'));
        $post->refresh();

        return response()->json(['data' => [
            'id' => $post->id,
            'consent_status' => $post->consent_status->value,
            'published' => $post->isPublished(),
        ]]);
    }

    /**
     * POST /api/v1/posts/{post}/request-consent — the post author nudges the
     * pending request again. Idempotent; re-notification is best-effort.
     */
    public function request(Request $request, PublicPost $post): JsonResponse
    {
        if ($request->user()->getKey() !== $post->author_id) {
            throw new ApiException('No existe.', 'NOT_FOUND', 404);
        }

        if ($post->consent_status !== ConsentStatus::Pending) {
            throw new ApiException('Esta publicación no está esperando consentimiento.', 'INVALID_STATE_TRANSITION', 409);
        }

        return response()->json(['data' => ['consent_status' => $post->consent_status->value]], 202);
    }

    private function assertAddressee(Request $request, PublicPost $post): void
    {
        $isAddressee = $post->consent_status === ConsentStatus::Pending
            && $post->sourceDelivery !== null
            && $post->sourceDelivery->sender_id === $request->user()->getKey();

        if (! $isAddressee) {
            throw new ApiException('No existe.', 'NOT_FOUND', 404);
        }
    }
}
