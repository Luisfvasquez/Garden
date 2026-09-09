<?php

declare(strict_types=1);

namespace App\Http\Requests\Letter;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Autosave: the front sends partial updates every ~5 s (docs/api/cartas.md).
 */
class UpdateLetterRequest extends FormRequest
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
            'title' => ['sometimes', 'nullable', 'string', 'max:200'],
            'body' => ['sometimes', 'array'],
            'body.type' => ['required_with:body', 'string', 'in:doc'],
            'style' => ['sometimes', 'array'],
        ];
    }
}
