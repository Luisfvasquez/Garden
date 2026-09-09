<?php

declare(strict_types=1);

namespace App\Http\Requests\Blog;

use App\Enums\PostType;
use App\Enums\PostVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'type' => ['required', Rule::enum(PostType::class)],
            'letter_delivery_id' => ['required_if:type,shared_letter', 'uuid'],
            'title' => ['required', 'string', 'max:160'],
            'body' => ['required', 'array'],
            'testimonial' => ['nullable', 'string', 'max:2000'],
            'tags' => ['sometimes', 'array', 'max:3'],
            'tags.*' => ['string', 'max:40'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::enum(PostVisibility::class)],
        ];
    }
}
