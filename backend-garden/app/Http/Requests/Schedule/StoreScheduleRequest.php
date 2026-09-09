<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use App\Enums\LeapDayPolicy;
use App\Enums\RecurrenceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScheduleRequest extends FormRequest
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
        $stepped = in_array($this->input('recurrence_type'), [
            RecurrenceType::Yearly->value,
            RecurrenceType::Monthly->value,
            RecurrenceType::Weekly->value,
        ], true);

        return [
            'name' => ['required', 'string', 'max:120'],

            'recipient' => ['required', 'array'],
            'recipient.postal_handle' => ['required', 'string'],

            'recurrence_type' => ['required', Rule::enum(RecurrenceType::class)],
            'anchor_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'local_time' => ['required', 'date_format:H:i'],
            'timezone' => ['required', 'timezone:all'],

            'occurrences_total' => [
                Rule::requiredIf($stepped),
                'integer',
                "between:1,{$max}",
            ],

            'custom_dates' => [
                Rule::requiredIf(fn (): bool => $this->input('recurrence_type') === RecurrenceType::CustomDates->value),
                'array',
                "max:{$max}",
            ],
            'custom_dates.*' => ['date_format:Y-m-d', 'after_or_equal:today', 'distinct'],

            'letter_id' => ['nullable', 'uuid'],
            'leap_day_policy' => ['sometimes', Rule::enum(LeapDayPolicy::class)],

            // Only date-triggered schedules in Fase 2; posthumous/inactivity
            // (with its legal notice) is Fase 4.
            'trigger_type' => ['sometimes', 'in:date'],

            'tier' => ['sometimes', 'in:express,standard,slow'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }
}
