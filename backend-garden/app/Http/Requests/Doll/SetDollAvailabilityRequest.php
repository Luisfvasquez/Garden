<?php

declare(strict_types=1);

namespace App\Http\Requests\Doll;

use Illuminate\Foundation\Http\FormRequest;

class SetDollAvailabilityRequest extends FormRequest
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
            'is_available' => ['required', 'boolean'],
        ];
    }
}
