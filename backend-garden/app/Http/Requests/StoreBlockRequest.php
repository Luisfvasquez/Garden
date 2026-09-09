<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBlockRequest extends FormRequest
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
            'user_id' => ['required_without:postal_handle', 'nullable', 'uuid'],
            'postal_handle' => ['required_without:user_id', 'nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
