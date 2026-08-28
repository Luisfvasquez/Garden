<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\FeatureMapResource;
use App\Services\FeatureFlags;

class FeatureFlagController extends Controller
{
    /**
     * GET /api/v1/features — public map of feature-flag state.
     */
    public function __invoke(FeatureFlags $flags): FeatureMapResource
    {
        return new FeatureMapResource($flags->all());
    }
}
