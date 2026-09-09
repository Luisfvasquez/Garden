<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReactionType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\PublicPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * heart | tear | flower | candle. Counts are private (docs/api/blog.md); the
 * response only reflects the caller's own reactions.
 */
class PostReactionController extends Controller
{
    public function store(Request $request, PublicPost $post): JsonResponse
    {
        $this->assertVisible($post);

        $data = $request->validate(['type' => ['required', Rule::enum(ReactionType::class)]]);

        $post->reactions()->firstOrCreate([
            'user_id' => $request->user()->getKey(),
            'type' => $data['type'],
        ]);

        return $this->mine($request, $post);
    }

    public function destroy(Request $request, PublicPost $post, string $type): JsonResponse
    {
        $reactionType = ReactionType::tryFrom($type);
        if ($reactionType === null) {
            throw new ApiException('Reacción no válida.', 'INVALID_TARGET', 422);
        }

        $post->reactions()
            ->where('user_id', $request->user()->getKey())
            ->where('type', $reactionType)
            ->delete();

        return $this->mine($request, $post);
    }

    private function assertVisible(PublicPost $post): void
    {
        if (! $post->isPublished()) {
            throw new ApiException('No existe.', 'NOT_FOUND', 404);
        }
    }

    private function mine(Request $request, PublicPost $post): JsonResponse
    {
        $mine = $post->reactions()
            ->where('user_id', $request->user()->getKey())
            ->pluck('type')
            ->map(fn (ReactionType $t) => $t->value)
            ->values();

        return response()->json(['data' => ['my_reactions' => $mine]]);
    }
}
