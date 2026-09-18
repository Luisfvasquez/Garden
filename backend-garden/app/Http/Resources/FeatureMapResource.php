<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wraps a `key => enabled` map for GET /api/v1/features.
 *
 * @property array<string, bool> $resource
 */
class FeatureMapResource extends JsonResource
{
    /**
     * Shape spelled out so the generated OpenAPI says "map of flag => bool"
     * instead of guessing from `JsonResource::$resource`, which is `mixed`.
     *
     * @return array{features: array<string, bool>}
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, bool> $features */
        $features = $this->resource;

        return ['features' => $features];
    }
}
