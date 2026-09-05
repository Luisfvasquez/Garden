<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TokenRequest;
use App\Http\Resources\TokenResource;
use App\Services\Auth\CredentialChecker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class TokenController extends Controller
{
    public function __invoke(TokenRequest $request, CredentialChecker $checker): JsonResponse
    {
        $data = $request->validated();

        $user = $checker->verify($data['email'], $data['password']);

        $minutes = (int) config('sanctum.expiration');
        $expiresAt = $minutes > 0 ? Carbon::now()->addMinutes($minutes) : null;

        $token = $user->createToken($data['device_name'], ['*'], $expiresAt);

        return (new TokenResource($token->plainTextToken, $expiresAt))
            ->response()
            ->setStatusCode(201);
    }
}
