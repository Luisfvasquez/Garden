<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DollProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The directory listing and detail view share this shape. `verified_at` and
 * `max_concurrent_requests` are owner-only — nobody else needs to know a Doll
 * is still pending review or how much room they have left.
 *
 * @mixin DollProfile
 */
class DollProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isOwner = $request->user()?->getKey() === $this->user_id;

        return [
            'id' => $this->id,
            'doll' => [
                'postal_handle' => $this->user->postal_handle,
                'display_name' => $this->user->displayName(),
                'avatar_url' => $this->user->avatarUrl(),
            ],
            'headline' => $this->headline,
            'bio' => $this->bio,
            'specialties' => $this->specialties,
            'languages' => $this->languages,
            'tone_tags' => $this->tone_tags,
            'rate_type' => $this->rate_type->value,
            'rate_amount' => $this->rate_amount,
            'currency' => $this->currency,
            'is_available' => $this->is_available,
            'rating_avg' => (float) $this->rating_avg,
            'rating_count' => $this->rating_count,
            'completed_requests_count' => $this->completed_requests_count,
            'response_time_avg_minutes' => $this->response_time_avg_minutes,
            'portfolio' => $this->portfolio ?? [],
            'verified_at' => $this->when($isOwner, fn () => $this->verified_at?->toIso8601ZuluString()),
            'max_concurrent_requests' => $this->when($isOwner, $this->max_concurrent_requests),
        ];
    }
}
