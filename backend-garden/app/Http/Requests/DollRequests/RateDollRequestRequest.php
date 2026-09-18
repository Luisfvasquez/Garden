<?php

declare(strict_types=1);

namespace App\Http\Requests\DollRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The client rates a completed request, once (docs/api/dolls.md).
 */
class RateDollRequestRequest extends FormRequest
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
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
