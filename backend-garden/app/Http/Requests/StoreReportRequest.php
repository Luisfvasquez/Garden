<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ReportableType;
use App\Enums\ReportCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
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
            'reportable_type' => ['required', Rule::enum(ReportableType::class)],
            'reportable_id' => ['required', 'uuid'],
            'category' => ['required', Rule::enum(ReportCategory::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
