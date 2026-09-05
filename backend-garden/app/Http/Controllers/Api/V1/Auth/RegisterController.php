<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\MeResource;
use App\Models\User;
use App\Models\UserSettings;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RegisterController extends Controller
{
    private const MINIMUM_AGE = 16;

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (Carbon::parse($data['birth_date'])->age < self::MINIMUM_AGE) {
            throw new ApiException(
                'Debes tener al menos 16 años para registrarte.',
                'UNDER_MINIMUM_AGE',
                422,
            );
        }

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'timezone' => $data['timezone'],
                'locale' => $data['locale'],
            ]);

            UserSettings::create(['user_id' => $user->id]);

            return $user;
        });

        event(new Registered($user));

        return (new MeResource($user))->response()->setStatusCode(201);
    }
}
