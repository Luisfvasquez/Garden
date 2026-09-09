<?php

declare(strict_types=1);

namespace App\Http\Requests\Mailbox;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveMailboxRequest extends FormRequest
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
        // Omitted => toggle.
        return ['archived' => ['sometimes', 'boolean']];
    }
}
