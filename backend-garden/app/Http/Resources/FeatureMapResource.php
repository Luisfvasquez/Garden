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
     * @return array<string, array<string, bool>>
     */
    public function toArray(Request $request): array
    {
        return [
            'features' => $this->resource,
        ];
    }
}
