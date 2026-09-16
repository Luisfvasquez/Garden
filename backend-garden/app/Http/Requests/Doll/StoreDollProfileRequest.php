<?php

declare(strict_types=1);

namespace App\Http\Requests\Doll;

use App\Enums\DollRateType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Requests the Doll role. Creates `doll_profiles` unverified — staff activate
 * it by hand (docs/api/dolls.md).
 */
class StoreDollProfileRequest extends FormRequest
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
            'headline' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'specialties' => ['sometimes', 'array', 'max:5'],
            'specialties.*' => ['string', 'max:40'],
            'languages' => ['sometimes', 'array', 'min:1', 'max:5'],
            'languages.*' => ['string', 'max:10'],
            'tone_tags' => ['sometimes', 'array', 'max:5'],
            'tone_tags.*' => ['string', 'max:40'],
            'rate_type' => ['sometimes', Rule::enum(DollRateType::class)],
            'rate_amount' => ['required_unless:rate_type,free', 'nullable', 'integer', 'min:0'],
            'currency' => ['required_unless:rate_type,free', 'nullable', 'string', 'size:3'],
        ];
    }
}
