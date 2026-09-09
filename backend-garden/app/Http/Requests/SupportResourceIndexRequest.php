<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SupportResourceIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public endpoint
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'country_code' => ['nullable', 'string', 'size:2', 'alpha'],
            'topic' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function countryCode(): ?string
    {
        $value = $this->query('country_code');

        return is_string($value) && $value !== '' ? strtoupper($value) : null;
    }

    public function topic(): ?string
    {
        $value = $this->query('topic');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
