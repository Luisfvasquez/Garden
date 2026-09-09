<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Letter\StoreLetterRequest;
use App\Http\Requests\Letter\UpdateLetterRequest;
use App\Http\Resources\LetterResource;
use App\Models\Letter;
use App\Services\Postal\LetterStyleCatalog;
use App\Support\CursorPage;
use App\Support\TiptapContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class LetterController extends Controller
{
    public function __construct(private readonly LetterStyleCatalog $styles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Letter::query()
            ->where('author_id', $request->user()->getKey())
            ->withCount('deliveries')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if ($request->query('status') === 'draft') {
            $query->where('is_locked', false);
        } elseif ($request->query('status') === 'sent') {
            $query->where('is_locked', true);
        }

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return LetterResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    public function store(StoreLetterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $author = $request->user();

        $openDrafts = Letter::query()
            ->where('author_id', $author->getKey())
            ->where('is_locked', false)
            ->count();

        if ($openDrafts >= (int) config('postal.limits.max_open_drafts')) {
            throw new ApiException(
                'Has alcanzado el máximo de borradores abiertos.',
                'DRAFT_LIMIT_REACHED',
                422,
            );
        }

        // Read the body raw: validated() prunes the nested Tiptap nodes we sanitise ourselves.
        [$body, $plain, $words] = $this->prepareBody((array) $request->input('body'));

        $letter = new Letter([
            'title' => $data['title'] ?? null,
            'body' => $body,
            'body_plain' => $plain,
            'word_count' => $words,
            'style' => $this->styles->sanitize($data['style'] ?? []),
            'kind' => $data['kind'] ?? 'direct',
            'in_reply_to_delivery_id' => $data['in_reply_to_delivery_id'] ?? null,
        ]);
        $letter->author_id = $author->getKey();
        $letter->save();

        return (new LetterResource($letter->loadCount('deliveries')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Letter $letter): LetterResource
    {
        $this->authorize('view', $letter);

        return new LetterResource($letter->load('attachments')->loadCount('deliveries'));
    }

    public function update(UpdateLetterRequest $request, Letter $letter): LetterResource
    {
        $this->authorize('update', $letter);
        $this->assertUnlocked($letter);

        $data = $request->validated();

        if (array_key_exists('title', $data)) {
            $letter->title = $data['title'];
        }

        if (array_key_exists('body', $data)) {
            [$body, $plain, $words] = $this->prepareBody((array) $request->input('body'));
            $letter->body = $body;
            $letter->body_plain = $plain;
            $letter->word_count = $words;
        }

        if (isset($data['style'])) {
            $letter->style = $this->styles->sanitize($data['style']);
        }

        $letter->save();

        return new LetterResource($letter->load('attachments')->loadCount('deliveries'));
    }

    public function destroy(Letter $letter): Response
    {
        $this->authorize('delete', $letter);
        $this->assertUnlocked($letter);

        $letter->delete();

        return response()->noContent();
    }

    public function preview(Request $request, Letter $letter): JsonResponse
    {
        $this->authorize('view', $letter);

        return response()->json([
            'data' => [
                'letter' => (new LetterResource($letter->load('attachments')->loadCount('deliveries')))
                    ->resolve($request),
                'resolved_style' => $this->resolveStyle($letter->style),
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $rawBody
     * @return array{0: array<string, mixed>, 1: string, 2: int}
     */
    private function prepareBody(array $rawBody): array
    {
        $body = TiptapContent::sanitize($rawBody);
        $plain = TiptapContent::toPlainText($body);

        $max = (int) config('postal.limits.body_max_chars');
        if (mb_strlen($plain) > $max) {
            throw ValidationException::withMessages([
                'body' => "La carta supera el máximo de {$max} caracteres.",
            ]);
        }

        return [$body, $plain, TiptapContent::wordCount($plain)];
    }

    private function assertUnlocked(Letter $letter): void
    {
        if ($letter->is_locked) {
            throw new ApiException('La carta ya fue enviada, no se puede editar.', 'LETTER_LOCKED', 409);
        }
    }

    /**
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function resolveStyle(array $style): array
    {
        $dimensions = [
            'paper' => 'papers',
            'font' => 'fonts',
            'ink' => 'inks',
            'stamp' => 'stamps',
            'border' => 'borders',
        ];

        $catalog = $this->styles->all();
        $resolved = [];

        foreach ($dimensions as $field => $dimension) {
            $key = $style[$field] ?? null;
            $resolved[$field] = is_string($key)
                ? collect($catalog[$dimension] ?? [])->firstWhere('key', $key)
                : null;
        }

        $seal = $style['seal'] ?? null;
        if (is_array($seal)) {
            $resolved['seal'] = [
                'color' => collect($catalog['seals'] ?? [])->firstWhere('key', $seal['color'] ?? null),
                'sigil' => collect($catalog['sigils'] ?? [])->firstWhere('key', $seal['sigil'] ?? null),
            ];
        }

        return $resolved;
    }
}
