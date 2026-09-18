<?php

declare(strict_types=1);

namespace App\Http\Requests\DollRequests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Doll shares a versioned draft (docs/api/dolls.md § POST /drafts).
 *
 * `draft_payload.body` is a Tiptap document; it is read raw in the controller
 * and sanitised there — `validated()` would prune the nested node tree, the
 * same reason `LetterController::store()` reads its body off the request.
 *
 * `version` is assigned server-side: letting the client pick it invites two
 * drafts claiming to be v3.
 */
class StoreDollDraftRequest extends FormRequest
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
            'draft_payload' => ['required', 'array'],
            'draft_payload.title' => ['nullable', 'string', 'max:200'],
            'draft_payload.body' => ['required', 'array'],
            'draft_payload.style' => ['sometimes', 'array'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
