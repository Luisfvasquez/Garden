<?php

declare(strict_types=1);

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;

class RespondConsentRequest extends FormRequest
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
            'granted' => ['required', 'boolean'],
        ];
    }
}
