<?php

declare(strict_types=1);

namespace App\Http\Requests\Me;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'pen_name' => [
                'sometimes',
                'nullable',
                'string',
                'min:2',
                'max:60',
                Rule::unique('users', 'pen_name')->ignore($this->user()->getKey()),
            ],
            'bio' => ['sometimes', 'nullable', 'string', 'max:500'],
            'country_code' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'timezone' => ['sometimes', 'required', 'timezone:all'],
            'locale' => ['sometimes', 'required', 'in:es,en'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code') && is_string($this->input('country_code'))) {
            $this->merge(['country_code' => strtoupper($this->input('country_code'))]);
        }
    }
}
