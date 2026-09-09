<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
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
            'platform' => ['required', 'in:web,ios,android'],
            'device_name' => ['nullable', 'string', 'max:120'],

            'endpoint' => ['required_if:platform,web', 'nullable', 'url', 'max:1000'],
            'public_key' => ['required_if:platform,web', 'nullable', 'string', 'max:255'],
            'auth_token' => ['required_if:platform,web', 'nullable', 'string', 'max:255'],

            'device_token' => ['required_unless:platform,web', 'nullable', 'string', 'max:512'],
        ];
    }
}
