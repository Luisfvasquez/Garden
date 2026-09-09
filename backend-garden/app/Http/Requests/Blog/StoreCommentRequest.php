<?php

declare(strict_types=1);

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
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
            'body' => ['required', 'string', 'max:2000'],
            'parent_id' => ['nullable', 'uuid'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ];
    }
}
