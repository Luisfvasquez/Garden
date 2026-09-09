<?php

declare(strict_types=1);

namespace App\Http\Requests\Blog;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the light-touch fields are editable after creation — not the type, the
 * source letter or the body (re-editing text would need re-moderation).
 */
class UpdatePostRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:160'],
            'testimonial' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'comments_enabled' => ['sometimes', 'boolean'],
            'tags' => ['sometimes', 'array', 'max:3'],
            'tags.*' => ['string', 'max:40'],
        ];
    }
}
