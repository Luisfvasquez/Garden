<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Doll\SetDollAvailabilityRequest;
use App\Http\Requests\Doll\StoreDollProfileRequest;
use App\Http\Requests\Doll\UpdateDollProfileRequest;
use App\Http\Resources\DollProfileResource;
use App\Models\DollProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDollProfileController extends Controller
{
    /**
     * GET /api/v1/me/doll-profile — not in the written contract, added so the
     * settings screen can show "pending review" vs "verified"
     * (docs/api/dolls.md deviation).
     */
    public function show(Request $request): DollProfileResource
    {
        $profile = DollProfile::query()->where('user_id', $request->user()->getKey())->with('user')->first();

        if ($profile === null) {
            throw new ApiException('Aún no has solicitado el rol de Doll.', 'NOT_FOUND', 404);
        }

        return new DollProfileResource($profile);
    }

    /**
     * POST /api/v1/me/doll-profile — requests the role. Staff verify by hand;
     * nothing here flips `users.role`.
     */
    public function store(StoreDollProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        if (DollProfile::query()->where('user_id', $user->getKey())->exists()) {
            throw new ApiException('Ya tienes una solicitud de perfil Doll.', 'INVALID_STATE_TRANSITION', 409);
        }

        $data = $request->validated();
        $profile = new DollProfile($data);
        $profile->user_id = $user->getKey();
        $profile->is_available = false; // nothing to be "available" for before verification
        $profile->save();

        return (new DollProfileResource($profile->load('user')))->response()->setStatusCode(201);
    }

    public function update(UpdateDollProfileRequest $request): JsonResponse
    {
        $profile = DollProfile::query()->where('user_id', $request->user()->getKey())->first();
        if ($profile === null) {
            throw new ApiException('No existe.', 'NOT_FOUND', 404);
        }

        $this->authorize('update', $profile);

        $profile->fill($request->validated())->save();

        return (new DollProfileResource($profile->load('user')))->response();
    }

    /**
     * POST /api/v1/me/doll-profile/availability — { is_available }.
     */
    public function availability(SetDollAvailabilityRequest $request): JsonResponse
    {
        $profile = DollProfile::query()->where('user_id', $request->user()->getKey())->first();
        if ($profile === null) {
            throw new ApiException('No existe.', 'NOT_FOUND', 404);
        }

        $this->authorize('update', $profile);

        $profile->forceFill(['is_available' => $request->boolean('is_available')])->save();

        return (new DollProfileResource($profile->load('user')))->response();
    }
}
