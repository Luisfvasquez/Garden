<?php

declare(strict_types=1);

namespace App\Http\Requests\DollRequests;

use App\Enums\DollChatMessageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A line of Doll chat. `system` is not accepted from a client — only the
 * platform writes those (contact-exchange warnings).
 */
class StoreDollChatMessageRequest extends FormRequest
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
            'type' => ['sometimes', Rule::in([DollChatMessageType::Text->value])],
            'body' => ['required', 'string', 'min:1', 'max:4000'],
        ];
    }
}
