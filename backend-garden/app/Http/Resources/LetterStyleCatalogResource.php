<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * GET /api/v1/letters/styles. `resource` is the raw catalogue array; every
 * entry is surfaced with `locked: false` (per-user unlocks are a future
 * extension — docs/api/cartas.md).
 *
 * @property array<string, array<int, array<string, mixed>>> $resource
 */
class LetterStyleCatalogResource extends JsonResource
{
    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function toArray(Request $request): array
    {
        $out = [];
        foreach ($this->resource as $dimension => $entries) {
            $out[$dimension] = array_map(
                static fn (array $entry): array => [...$entry, 'locked' => false],
                $entries,
            );
        }

        return $out;
    }
}
