<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Report
 */
class ReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reportable_type' => $this->reportable_type->value,
            'reportable_id' => $this->reportable_id,
            'category' => $this->category->value,
            'severity' => $this->severity->value,
            'status' => $this->status->value,
            'details' => $this->details,
            'created_at' => $this->created_at->toIso8601ZuluString(),
        ];
    }
}
