<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LetterSchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LetterSchedule
 */
class ScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'recipient' => $this->recipient === null ? null : [
                'postal_handle' => $this->recipient->postal_handle,
                'display_name' => $this->recipient->displayName(),
            ],
            'recurrence_type' => $this->recurrence_type->value,
            'anchor_date' => $this->anchor_date->toDateString(),
            'local_time' => $this->local_time,
            'timezone' => $this->timezone,
            'occurrences_total' => $this->occurrences_total,
            'custom_dates' => $this->custom_dates ?? [],
            'leap_day_policy' => $this->leap_day_policy->value,
            'trigger_type' => $this->trigger_type->value,
            'letter_id' => $this->letter_id,
            'tier' => $this->tier->value,
            'is_anonymous' => $this->is_anonymous,
            'status' => $this->status->value,
            'created_at' => $this->created_at->toIso8601ZuluString(),
            'updated_at' => $this->updated_at->toIso8601ZuluString(),
        ];
    }
}
