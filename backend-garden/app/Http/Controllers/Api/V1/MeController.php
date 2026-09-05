<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateProfileRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class MeController extends Controller
{
    public function show(Request $request): MeResource
    {
        return new MeResource($request->user());
    }

    public function update(UpdateProfileRequest $request): MeResource
    {
        $user = $request->user();
        $user->fill($request->validated())->save();

        return new MeResource($user);
    }

    public function deactivate(Request $request): Response
    {
        $user = $request->user();
        $user->forceFill([
            'status' => UserStatus::Deactivated,
            'deactivated_at' => now(),
        ])->save();

        $this->revokeAllAccess($request, $user);

        return response()->noContent();
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        $deletesAt = now()->addDays(30);

        $user->forceFill(['deletes_at' => $deletesAt])->save();

        return response()->json([
            'data' => ['deletes_at' => $deletesAt->toIso8601ZuluString()],
        ], 202);
    }

    private function revokeAllAccess(Request $request, User $user): void
    {
        $user->tokens()->delete();

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }
}
