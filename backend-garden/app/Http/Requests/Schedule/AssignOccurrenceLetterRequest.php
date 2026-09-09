<?php

declare(strict_types=1);

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /schedules/{id}/occurrences/{date}/letter — pin a letter to one date, or
 * clear it with `letter_id: null`.
 */
class AssignOccurrenceLetterRequest extends FormRequest
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
        return [
            'letter_id' => ['present', 'nullable', 'uuid'],
        ];
    }
}
