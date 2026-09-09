<?php

declare(strict_types=1);

namespace App\Http\Requests\Mailbox;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The single reply the recipient of a random letter may send back down the same
 * anonymous channel (docs/api/botella-al-mar.md).
 */
class ReplyAnonymousRequest extends FormRequest
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
            'body' => ['required', 'array'],
            'tier' => ['sometimes', 'in:express,standard,slow'],
        ];
    }
}
