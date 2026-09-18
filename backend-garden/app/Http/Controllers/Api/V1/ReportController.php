<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReportableType;
use App\Enums\ReportCategory;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\DollChatMessage;
use App\Models\Report;
use App\Models\User;
use App\Services\Moderation\CriticalAlertDispatcher;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function __construct(private readonly CriticalAlertDispatcher $alerts) {}

    public function store(StoreReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = ReportableType::from($data['reportable_type']);
        $category = ReportCategory::from($data['category']);
        $me = $request->user();

        $modelClass = $type->modelClass();

        // A client never holds a person's uuid — only their public handle. For
        // `user` targets `reportable_handle` stands in, the same deviation
        // /blocks and /doll-requests already make.
        if (! isset($data['reportable_id'])) {
            if ($type !== ReportableType::User) {
                throw new ApiException('Ese tipo de contenido se reporta por id.', 'INVALID_TARGET', 422);
            }

            $data['reportable_id'] = User::query()
                ->where('postal_handle', $data['reportable_handle'])
                ->value('id');

            if ($data['reportable_id'] === null) {
                throw new ApiException('El contenido reportado no existe.', 'NOT_FOUND', 404);
            }
        }

        if (! $modelClass::query()->whereKey($data['reportable_id'])->exists()) {
            throw new ApiException('El contenido reportado no existe.', 'NOT_FOUND', 404);
        }

        if ($type === ReportableType::User && $data['reportable_id'] === $me->getKey()) {
            throw new ApiException('No puedes reportarte a ti mismo.', 'INVALID_TARGET', 422);
        }

        // A Doll chat is visible to exactly two people. Reporting is the one
        // place that takes an id from outside, so it gets the same isolation
        // as the chat itself: a non-participant learns nothing, not even that
        // the message exists (docs/api/dolls.md § Salvaguardas).
        if ($type === ReportableType::DollChatMessage) {
            $isParticipant = DollChatMessage::query()
                ->whereKey($data['reportable_id'])
                ->whereHas('dollRequest', fn ($q) => $q
                    ->where('client_id', $me->getKey())
                    ->orWhere('doll_id', $me->getKey()))
                ->exists();

            if (! $isParticipant) {
                throw new ApiException('El contenido reportado no existe.', 'NOT_FOUND', 404);
            }
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
            // Skips the moderation queue entirely (docs/moderacion.md §Escalado crítico).
            $this->alerts->alert(
                'Critical report filed',
                "{$me->postal_handle} reported {$type->value}:{$data['reportable_id']} as {$category->value}.",
                ['report_id' => $report->id, 'category' => $category->value, 'reportable' => "{$type->value}:{$data['reportable_id']}"],
            );
        }

        return (new ReportResource($report))->response()->setStatusCode(201);
    }
}
