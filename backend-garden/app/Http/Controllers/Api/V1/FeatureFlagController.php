<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatureMapResource;
use App\Services\FeatureFlags;
use Dedoc\Scramble\Attributes\Response;

class FeatureFlagController extends Controller
{
    /**
     * GET /api/v1/features — public map of feature-flag state.
     *
     * The response shape is declared explicitly because Scramble infers
     * `JsonResource::$resource` as `mixed` for a resource that wraps an array
     * instead of a model, and would otherwise publish `features: string`.
     */
    #[Response(200, type: 'array{data: array{features: array<string, bool>}}')]
    public function __invoke(FeatureFlags $flags): FeatureMapResource
    {
        return new FeatureMapResource($flags->all());
    }
}
