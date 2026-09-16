<?php

declare(strict_types=1);

namespace App\Http\Requests\Doll;

use App\Enums\DollRateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDollProfileRequest extends FormRequest
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
            'headline' => ['sometimes', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'specialties' => ['sometimes', 'array', 'max:5'],
            'specialties.*' => ['string', 'max:40'],
            'languages' => ['sometimes', 'array', 'min:1', 'max:5'],
            'languages.*' => ['string', 'max:10'],
            'tone_tags' => ['sometimes', 'array', 'max:5'],
            'tone_tags.*' => ['string', 'max:40'],
            'rate_type' => ['sometimes', Rule::enum(DollRateType::class)],
            'rate_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
        ];
    }
}
