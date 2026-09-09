<?php

declare(strict_types=1);

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLetterRequest extends FormRequest
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
            'title' => ['nullable', 'string', 'max:200'],
            'body' => ['required', 'array'],
            'body.type' => ['required', 'string', 'in:doc'],
            'style' => ['sometimes', 'array'],
            'kind' => ['sometimes', 'string', 'in:direct,unaddressed'],
            'in_reply_to_delivery_id' => [
                'nullable',
                'uuid',
                // You can only reply to mail you actually received.
                Rule::exists('letter_deliveries', 'id')->where(
                    fn ($q) => $q->where('recipient_id', $this->user()->getKey())
                ),
            ],
        ];
    }
}
