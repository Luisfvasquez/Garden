<?php

declare(strict_types=1);

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;

class SendRandomLetterRequest extends FormRequest
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
            'tier' => ['required', 'in:express,standard,slow'],
            // A short note shown to the stranger. Accepted for forward
            // compatibility; not persisted yet (docs/api/botella-al-mar.md).
            'message_to_stranger' => ['nullable', 'string', 'max:500'],
        ];
    }
}
