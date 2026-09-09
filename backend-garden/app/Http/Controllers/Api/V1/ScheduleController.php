<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RecurrenceType;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\AssignOccurrenceLetterRequest;
use App\Http\Requests\Schedule\StoreScheduleRequest;
use App\Http\Requests\Schedule\UpdateScheduleRequest;
use App\Http\Resources\ScheduleResource;
use App\Models\Letter;
use App\Models\LetterSchedule;
use App\Models\User;
use App\Services\Scheduling\OccurrenceGenerator;
use App\Services\Scheduling\OccurrenceMaterializer;
use App\Services\Scheduling\OccurrenceTimeline;
use App\Support\CursorPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ScheduleController extends Controller
{
    public function __construct(
        private readonly OccurrenceGenerator $generator,
        private readonly OccurrenceMaterializer $materializer,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $page = LetterSchedule::query()
            ->where('user_id', $request->user()->getKey())
            ->with('recipient')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return ScheduleResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    public function store(StoreScheduleRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        $recipient = $this->resolveRecipient($user, $data['recipient']['postal_handle']);
        $letterId = isset($data['letter_id']) ? $this->assertOwnedLetter($user, $data['letter_id'])->getKey() : null;

        $schedule = new LetterSchedule([
            'name' => $data['name'],
            'recurrence_type' => $data['recurrence_type'],
            'anchor_date' => $data['anchor_date'],
            'local_time' => $data['local_time'],
            'timezone' => $data['timezone'],
            'occurrences_total' => $data['recurrence_type'] === RecurrenceType::Once->value
                ? 1
                : ($data['occurrences_total'] ?? null),
            'custom_dates' => $data['custom_dates'] ?? null,
            'leap_day_policy' => $data['leap_day_policy'] ?? 'feb_28',
            'trigger_type' => $data['trigger_type'] ?? 'date',
            'letter_id' => $letterId,
            'tier' => $data['tier'] ?? 'standard',
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
        ]);
        $schedule->user_id = $user->getKey();
        $schedule->recipient_id = $recipient->getKey();
        $schedule->save();

        $this->materialiseNow($schedule);

        return (new ScheduleResource($schedule->load('recipient')))->response()->setStatusCode(201);
    }

    public function show(Request $request, LetterSchedule $schedule): ScheduleResource
    {
        $this->authorize('view', $schedule);

        return new ScheduleResource($schedule->load('recipient'));
    }

    public function update(UpdateScheduleRequest $request, LetterSchedule $schedule): ScheduleResource
    {
        $this->authorize('update', $schedule);
        $data = $request->validated();

        if (array_key_exists('letter_id', $data)) {
            $data['letter_id'] = $data['letter_id'] === null
                ? null
                : $this->assertOwnedLetter($request->user(), $data['letter_id'])->getKey();
        }

        $schedule->fill($data)->save();
        $this->materialiseNow($schedule->refresh());

        return new ScheduleResource($schedule->load('recipient'));
    }

    public function destroy(LetterSchedule $schedule): Response
    {
        $this->authorize('delete', $schedule);

        $schedule->delete();

        return response()->noContent();
    }

    public function pause(LetterSchedule $schedule): ScheduleResource
    {
        $this->authorize('update', $schedule);
        $schedule->pause();

        return new ScheduleResource($schedule->load('recipient'));
    }

    public function resume(LetterSchedule $schedule): ScheduleResource
    {
        $this->authorize('update', $schedule);
        $schedule->resume();
        $this->materialiseNow($schedule);

        return new ScheduleResource($schedule->load('recipient'));
    }

    public function occurrences(LetterSchedule $schedule, OccurrenceTimeline $timeline): JsonResponse
    {
        $this->authorize('view', $schedule);

        return response()->json($timeline->build($schedule));
    }

    public function assignLetter(
        AssignOccurrenceLetterRequest $request,
        LetterSchedule $schedule,
        string $date,
    ): JsonResponse {
        $this->authorize('update', $schedule);

        $occurrenceDates = array_map(
            static fn ($o): string => $o->date,
            $this->generator->all($schedule),
        );

        if (! in_array($date, $occurrenceDates, true)) {
            throw new ApiException('Esa fecha no es una ocurrencia de esta programación.', 'NOT_FOUND', 404);
        }

        $letterId = $request->validated('letter_id');
        if ($letterId !== null) {
            $this->assertOwnedLetter($request->user(), $letterId);
        }

        $occurrence = $schedule->occurrences()->firstOrNew(['occurrence_date' => $date]);

        if ($occurrence->delivery_id !== null) {
            throw new ApiException('Esa ocurrencia ya se materializó, no se puede cambiar la carta.', 'INVALID_STATE_TRANSITION', 409);
        }

        $occurrence->fill(['letter_id' => $letterId])->save();

        $this->materialiseNow($schedule);

        return response()->json(['data' => ['occurrence_date' => $date, 'letter_id' => $letterId]]);
    }

    private function resolveRecipient(User $user, string $handle): User
    {
        if ($handle === $user->postal_handle) {
            throw new ApiException('No puedes programarte cartas a ti mismo.', 'INVALID_TARGET', 422);
        }

        $recipient = User::query()->where('postal_handle', $handle)->first();

        if ($recipient === null || ! $recipient->status->canAuthenticate()) {
            throw new ApiException('El identificador postal no existe.', 'RECIPIENT_NOT_FOUND', 422);
        }

        return $recipient;
    }

    private function assertOwnedLetter(User $user, string $letterId): Letter
    {
        $letter = Letter::query()->whereKey($letterId)->first();

        if ($letter === null || $letter->author_id !== $user->getKey()) {
            throw new ApiException('La carta no existe.', 'NOT_FOUND', 404);
        }

        return $letter;
    }

    /**
     * Materialise this schedule's next 90 days now, so the timeline is populated
     * without waiting for the nightly job. Idempotent.
     */
    private function materialiseNow(LetterSchedule $schedule): void
    {
        if (! $schedule->status->isMaterialisable()) {
            return;
        }

        $schedule->loadMissing(['user', 'recipient']);
        $horizon = (int) config('postal.schedules.materialize_horizon_days', 90);

        foreach ($this->generator->upcoming($schedule, CarbonImmutable::now(), $horizon) as $occurrence) {
            $this->materializer->materialise($schedule, $occurrence);
        }

        $schedule->forceFill(['last_materialized_at' => now()])->save();
    }
}
