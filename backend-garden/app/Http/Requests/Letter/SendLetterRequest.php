<?php

declare(strict_types=1);

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;

class SendLetterRequest extends FormRequest
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
            'recipients' => ['required', 'array', 'min:1', 'max:10'],
            'recipients.*.postal_handle' => ['required', 'string', 'distinct'],

            // Random ("botella al mar") arrives in Fase 2.
            'delivery.mode' => ['sometimes', 'in:direct'],
            'delivery.tier' => ['required', 'in:express,standard,slow'],
            'delivery.arrive_at' => ['nullable', 'date', 'after:now'],
            'delivery.arrive_timezone' => ['nullable', 'timezone:all'],

            'is_anonymous' => ['sometimes', 'boolean'],
            'reveal_sender_at' => ['nullable', 'date', 'after:now'],
            'allow_read_receipt' => ['sometimes', 'boolean'],
        ];
    }
}
