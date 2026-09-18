<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ConsentStatus;
use App\Enums\ModerationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Blog\StorePostRequest;
use App\Http\Requests\Blog\UpdatePostRequest;
use App\Http\Resources\PostResource;
use App\Models\PublicPost;
use App\Models\Tag;
use App\Services\Blog\BlogPublisher;
use App\Support\CursorPage;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PostController extends Controller
{
    public function __construct(private readonly BlogPublisher $publisher) {}

    /**
     * GET /api/v1/posts — public feed. ?type=&tag=&sort=recent|featured
     */
    #[QueryParameter(
        'q',
        'Búsqueda full-text sobre título y cuerpo (Postgres, configuración `spanish`). Acepta comillas para frase exacta y `-palabra` para excluir. Ordena por relevancia.',
        required: false,
        type: 'string',
        example: 'cartas a mi padre',
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $terms = $request->query('q');
        $searching = is_string($terms) && trim($terms) !== '';

        // Searching pages over a ranked derived table (see PublicPost::rankedSubquery);
        // every other filter below applies to it unchanged, because the derived
        // table keeps the name `public_posts`.
        $query = $searching
            ? PublicPost::query()->fromSub(PublicPost::rankedSubquery(trim((string) $terms)), 'public_posts')
            : PublicPost::query();

        $query
            ->publiclyVisible()
            ->with(['author', 'tags', 'reactions'])
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('tag'), fn ($q, $tag) => $q->whereHas(
                'tags',
                fn ($t) => $t->where('slug', $tag),
            ));

        if ($searching) {
            // Relevance first; recency and id break ties and keep the cursor unique.
            $query->orderByDesc('search_rank');
        }

        // The featured feed is curated by hand, never algorithmic
        // (docs/api/blog.md) — for now it is just reverse-chronological.
        $query->orderByDesc('published_at')->orderByDesc('id');

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return PostResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    public function store(StorePostRequest $request): JsonResponse
    {
        $post = $this->publisher->createPost($request->user(), $request->validated());

        // 202 while it waits for the original author's consent or human review;
        // 201 when it is already live (docs/api/blog.md).
        $status = ($post->consent_status === ConsentStatus::Pending
            || $post->moderation_status === ModerationStatus::Flagged) ? 202 : 201;

        return (new PostResource($post))->response()->setStatusCode($status);
    }

    public function show(Request $request, string $slug): PostResource
    {
        $post = PublicPost::query()->where('slug', $slug)->with(['author', 'tags', 'reactions'])->firstOrFail();

        $this->authorize('view', $post);

        return new PostResource($post);
    }

    public function update(UpdatePostRequest $request, PublicPost $post): PostResource
    {
        $this->authorize('update', $post);

        $data = $request->validated();
        $post->fill(array_intersect_key($data, array_flip(['title', 'testimonial', 'comments_enabled'])));
        $post->save();

        if (array_key_exists('tags', $data)) {
            $ids = array_map(
                fn ($label): string => Tag::fromLabel((string) $label)->getKey(),
                (array) $data['tags'],
            );
            $post->tags()->sync($ids);
        }

        return new PostResource($post->load(['author', 'tags', 'reactions']));
    }

    public function destroy(PublicPost $post): Response
    {
        $this->authorize('delete', $post);
        $post->delete();

        return response()->noContent();
    }
}
