<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Auth\CredentialChecker;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function __invoke(LoginRequest $request, CredentialChecker $checker): Response
    {
        $data = $request->validated();

        $user = $checker->verify($data['email'], $data['password']);

        Auth::guard('web')->login($user, (bool) ($data['remember'] ?? false));

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->noContent();
    }
}
