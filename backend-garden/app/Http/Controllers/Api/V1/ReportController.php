<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportableType;
use App\Enums\ReportCategory;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ReportController extends Controller
{
    public function store(StoreReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = ReportableType::from($data['reportable_type']);
        $category = ReportCategory::from($data['category']);
        $me = $request->user();

        $modelClass = $type->modelClass();
        if ($modelClass === null) {
            throw new ApiException('Aún no se puede reportar este tipo de contenido.', 'INVALID_TARGET', 422);
        }

        if (! $modelClass::query()->whereKey($data['reportable_id'])->exists()) {
            throw new ApiException('El contenido reportado no existe.', 'NOT_FOUND', 404);
        }

        if ($type === ReportableType::User && $data['reportable_id'] === $me->getKey()) {
            throw new ApiException('No puedes reportarte a ti mismo.', 'INVALID_TARGET', 422);
        }

        $report = Report::query()->updateOrCreate(
            [
                'reporter_id' => $me->getKey(),
                'reportable_type' => $type,
                'reportable_id' => $data['reportable_id'],
            ],
            [
                'category' => $category,
                'details' => $data['details'] ?? null,
                'severity' => $category->severity(),
            ],
        );

        if ($report->severity->isCritical()) {
            // Skips the moderation queue — alerting (email/Slack) lands with the notifications work.
            Log::critical('Critical report filed', [
                'report_id' => $report->id,
                'category' => $category->value,
                'reportable' => "{$type->value}:{$data['reportable_id']}",
            ]);
        }

        return (new ReportResource($report))->response()->setStatusCode(201);
    }
}
