<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportResourceIndexRequest;
use App\Http\Resources\SupportResourceResource;
use App\Models\SupportResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupportResourceController extends Controller
{
    /**
     * GET /api/v1/support-resources?country_code=ES&topic=self_harm
     *
     * Public. Returns the country's helplines plus the global fallback rows,
     * most prominent first. Surfaced by the moderation filter on a self-harm
     * signal and always reachable from settings.
     */
    public function index(SupportResourceIndexRequest $request): AnonymousResourceCollection
    {
        $query = SupportResource::query()->forCountry($request->countryCode());

        if (($topic = $request->topic()) !== null) {
            $query->where('topic', $topic);
        }

        return SupportResourceResource::collection($query->get());
    }
}
