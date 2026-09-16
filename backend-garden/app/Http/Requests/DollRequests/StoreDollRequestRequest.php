<?php

declare(strict_types=1);

namespace App\Http\Requests\DollRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `doll_handle` deviates from the contract's literal `doll_id` (docs/api/dolls.md)
 * — the client never has the Doll's raw user id, only the public handle shown
 * in the directory. The controller resolves it server-side.
 */
class StoreDollRequestRequest extends FormRequest
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
            'doll_handle' => ['required', 'string'],
            'occasion' => ['required', 'string', 'max:200'],
            'brief_notes' => ['nullable', 'string', 'max:3000'],
            'target_recipient_hint' => ['nullable', 'string', 'max:500'],
            'desired_tone' => ['sometimes', 'array', 'max:5'],
            'desired_tone.*' => ['string', 'max:40'],
            'deadline_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
