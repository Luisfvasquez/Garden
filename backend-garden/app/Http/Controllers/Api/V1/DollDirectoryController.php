<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DollProfileResource;
use App\Models\DollProfile;
use App\Models\User;
use App\Support\CursorPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The public-facing side of the Doll directory — only ever verified profiles
 * (docs/api/dolls.md). Unverified ones are invisible here even to staff; they
 * review from the Filament panel instead.
 */
class DollDirectoryController extends Controller
{
    /**
     * GET /api/v1/dolls?specialty=&language=&available=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = DollProfile::query()
            ->verified()
            ->with('user')
            ->when($request->query('specialty'), fn ($q, $v) => $q->whereJsonContains('specialties', $v))
            ->when($request->query('language'), fn ($q, $v) => $q->whereJsonContains('languages', $v))
            ->when($request->has('available'), fn ($q) => $q->where('is_available', $request->boolean('available')));

        $query->orderByDesc('rating_avg')->orderByDesc('completed_requests_count')->orderBy('id');

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return DollProfileResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    /**
     * GET /api/v1/dolls/{handle} — `{handle}` is the Doll's `postal_handle`,
     * the same public identifier used everywhere else (`GET /users/{handle}`,
     * recipients on send). A deviation from the contract's literal `{id}`
     * (docs/api/dolls.md) for URL-friendliness and consistency.
     */
    public function show(string $handle): DollProfileResource
    {
        $user = User::query()->where('postal_handle', $handle)->firstOrFail();
        $profile = DollProfile::query()->where('user_id', $user->getKey())->with('user')->firstOrFail();

        $this->authorize('view', $profile);

        return new DollProfileResource($profile);
    }
}
