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
            // El cliente nunca tiene el uuid de una persona: sólo su handle
            // público. Para `user` se acepta `reportable_handle` en su lugar,
            // misma desviación que ya hacen /blocks y /doll-requests.
            'reportable_id' => ['required_without:reportable_handle', 'uuid'],
            'reportable_handle' => ['required_without:reportable_id', 'string'],
            'category' => ['required', Rule::enum(ReportCategory::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
