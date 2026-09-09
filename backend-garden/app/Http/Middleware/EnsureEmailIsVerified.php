<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use Closure;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for actions that require a verified email (sending letters, bottle at
 * sea, publishing, Doll requests). Returns the contract's `EMAIL_NOT_VERIFIED`.
 */
class EnsureEmailIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail()) {
            throw new ApiException('Verifica tu correo para continuar.', 'EMAIL_NOT_VERIFIED', 403);
        }

        return $next($request);
    }
}
