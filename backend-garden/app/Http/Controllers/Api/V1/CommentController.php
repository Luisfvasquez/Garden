<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\PublicPost;
use App\Services\Blog\BlogPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CommentController extends Controller
{
    public function __construct(private readonly BlogPublisher $publisher) {}

    /**
     * GET /api/v1/posts/{post}/comments — public. Top-level comments with their
     * one level of replies.
     */
    public function index(PublicPost $post): AnonymousResourceCollection
    {
        $comments = $post->comments()
            ->visible()
            ->whereNull('parent_id')
            ->with(['author', 'replies' => fn ($q) => $q->visible()->with('author')->orderBy('created_at')])
            ->orderBy('created_at')
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, PublicPost $post): JsonResponse
    {
        $comment = $this->publisher->createComment($request->user(), $post, $request->validated());

        return (new CommentResource($comment->load('author')))
            ->response()
            ->setStatusCode($comment->published_at === null ? 202 : 201);
    }

    public function destroy(Comment $comment): Response
    {
        $this->authorize('delete', $comment);
        $comment->delete();

        return response()->noContent();
    }
}
