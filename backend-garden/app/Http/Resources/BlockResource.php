<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Block;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Block
 */
class BlockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this->blocked;

        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'user' => [
                'id' => $this->blocked_id,
                'postal_handle' => $user?->postal_handle,
                'display_name' => $user?->displayName(),
                'avatar_url' => $user?->avatarUrl(),
            ],
        ];
    }
}
