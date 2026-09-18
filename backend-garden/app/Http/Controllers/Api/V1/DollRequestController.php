<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DollRequests\RateDollRequestRequest;
use App\Http\Requests\DollRequests\StoreDollRequestRequest;
use App\Http\Resources\DollRequestResource;
use App\Models\DollProfile;
use App\Models\DollRequest;
use App\Models\User;
use App\Services\Moderation\PiiScanner;
use App\Support\CursorPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DollRequestController extends Controller
{
    public function __construct(private readonly PiiScanner $pii) {}

    /**
     * GET /api/v1/doll-requests?role=client|doll&status=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $userId = $request->user()->getKey();
        $role = $request->query('role');

        $query = DollRequest::query()
            ->when(
                $role === 'client',
                fn ($q) => $q->forClient($userId),
                fn ($q) => $q->when(
                    $role === 'doll',
                    fn ($q) => $q->forDoll($userId),
                    fn ($q) => $q->where(fn ($q) => $q->forClient($userId)->orWhere->forDoll($userId)),
                )
            )
            ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
            ->with(['client', 'doll'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $page = $query->cursorPaginate(CursorPage::perPage((int) $request->query('per_page', '20')));

        return DollRequestResource::collection($page->getCollection())
            ->additional(['meta' => CursorPage::meta($page)]);
    }

    /**
     * POST /api/v1/doll-requests — `doll_handle` resolves to the Doll's
     * `doll_profiles` row; rejects if unverified, unavailable, or at capacity.
     */
    public function store(StoreDollRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        $dollUser = User::query()->where('postal_handle', $data['doll_handle'])->first();
        $profile = $dollUser === null
            ? null
            : DollProfile::query()->verified()->where('user_id', $dollUser->getKey())->first();

        if ($profile === null) {
            throw new ApiException('Esa Doll no existe.', 'NOT_FOUND', 404);
        }

        if (! $profile->is_available || ! $profile->hasCapacity()) {
            throw new ApiException('Esta Doll no puede aceptar más solicitudes ahora mismo.', 'DOLL_UNAVAILABLE', 409);
        }

        if (isset($data['target_recipient_hint']) && $this->pii->hasPii($data['target_recipient_hint'])) {
            throw new ApiException(
                'La pista sobre el destinatario no puede incluir datos de contacto.',
                'PII_DETECTED',
                422,
            );
        }

        $doll = new DollRequest([
            'occasion' => $data['occasion'],
            'brief_notes' => $data['brief_notes'] ?? null,
            'target_recipient_hint' => $data['target_recipient_hint'] ?? null,
            'desired_tone' => $data['desired_tone'] ?? [],
            'deadline_at' => $data['deadline_at'] ?? null,
        ]);
        $doll->client_id = $request->user()->getKey();
        $doll->doll_id = $dollUser->getKey();
        $doll->expires_at = now()->addHours(48);
        $doll->save();

        return (new DollRequestResource($doll->load(['client', 'doll'])))->response()->setStatusCode(201);
    }

    public function show(Request $request, DollRequest $dollRequest): DollRequestResource
    {
        $this->authorize('view', $dollRequest);

        return new DollRequestResource($dollRequest->load(['client', 'doll']));
    }

    public function accept(DollRequest $dollRequest): DollRequestResource
    {
        $this->authorize('respond', $dollRequest);
        $dollRequest->accept();

        return new DollRequestResource($dollRequest->load(['client', 'doll']));
    }

    public function reject(DollRequest $dollRequest): DollRequestResource
    {
        $this->authorize('respond', $dollRequest);
        $dollRequest->reject();

        return new DollRequestResource($dollRequest->load(['client', 'doll']));
    }

    public function start(DollRequest $dollRequest): DollRequestResource
    {
        $this->authorize('respond', $dollRequest);
        $dollRequest->start();

        return new DollRequestResource($dollRequest->load(['client', 'doll']));
    }

    public function cancel(DollRequest $dollRequest): DollRequestResource
    {
        $this->authorize('cancel', $dollRequest);
        $dollRequest->cancel();

        return new DollRequestResource($dollRequest->load(['client', 'doll']));
    }

    /**
     * POST /api/v1/doll-requests/{id}/rate — the client rates a completed
     * request, once. The profile aggregate is NOT touched here: it is derived,
     * and RecalculateDollRatingsJob owns it (hourly). Writing both from here
     * would make the average drift the first time anything is corrected.
     */
    public function rate(RateDollRequestRequest $request, DollRequest $dollRequest): DollRequestResource
    {
        $this->authorize('rate', $dollRequest);

        $data = $request->validated();
        $dollRequest->rate((int) $data['rating'], $data['comment'] ?? null);

        return new DollRequestResource($dollRequest->load(['client', 'doll']));
    }
}
