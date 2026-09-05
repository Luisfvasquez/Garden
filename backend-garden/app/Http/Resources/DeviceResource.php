<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A personal access token = one mobile session (docs/api/auth.md GET /auth/devices).
 *
 * @mixin PersonalAccessToken
 */
class DeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $current = $request->user()?->currentAccessToken();

        return [
            'id' => $this->id,
            'device_name' => $this->name,
            'last_used_at' => $this->last_used_at?->toIso8601ZuluString(),
            'created_at' => $this->created_at?->toIso8601ZuluString(),
            'current' => $current instanceof PersonalAccessToken && $current->getKey() === $this->getKey(),
        ];
    }
}
