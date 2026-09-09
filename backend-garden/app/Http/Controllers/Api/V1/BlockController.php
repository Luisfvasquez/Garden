<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBlockRequest;
use App\Http\Resources\BlockResource;
use App\Models\Block;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Blocking is silent: the blocked user is never told, and their letters turn
 * `blocked` while still reporting as `delivered` to them (ADR-0007).
 */
class BlockController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $blocks = Block::query()
            ->where('blocker_id', $request->user()->getKey())
            ->with('blocked')
            ->latest('created_at')
            ->limit(200)
            ->get();

        return BlockResource::collection($blocks);
    }

    public function store(StoreBlockRequest $request): JsonResponse
    {
        $me = $request->user();

        $target = User::query()
            ->when(
                $request->filled('user_id'),
                fn ($q) => $q->whereKey($request->input('user_id')),
                fn ($q) => $q->where('postal_handle', $request->input('postal_handle')),
            )
            ->where('status', UserStatus::Active)
            ->first();

        if ($target === null) {
            throw new ApiException('No se encontró a esa persona.', 'NOT_FOUND', 404);
        }

        if ($target->getKey() === $me->getKey()) {
            throw new ApiException('No puedes bloquearte a ti mismo.', 'CANNOT_BLOCK_SELF', 422);
        }

        $block = Block::query()->updateOrCreate(
            ['blocker_id' => $me->getKey(), 'blocked_id' => $target->getKey()],
            ['reason' => $request->input('reason')],
        );

        return (new BlockResource($block->load('blocked')))->response()->setStatusCode(201);
    }

    public function destroy(Request $request, string $userId): Response
    {
        Block::query()
            ->where('blocker_id', $request->user()->getKey())
            ->where('blocked_id', $userId)
            ->delete();

        return response()->noContent();
    }
}
