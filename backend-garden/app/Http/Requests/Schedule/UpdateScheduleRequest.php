<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use App\Enums\LeapDayPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Editable after creation: the name, the default letter, the send options and
 * the length. The recurrence shape and the frozen timezone are not — changing
 * those is a new schedule (docs/api/programaciones.md).
 */
class UpdateScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = (int) config('postal.schedules.max_occurrences', 60);

        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'letter_id' => ['sometimes', 'nullable', 'uuid'],
            'occurrences_total' => ['sometimes', 'nullable', 'integer', "between:1,{$max}"],
            'leap_day_policy' => ['sometimes', Rule::enum(LeapDayPolicy::class)],
            'tier' => ['sometimes', 'in:express,standard,slow'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }
}
