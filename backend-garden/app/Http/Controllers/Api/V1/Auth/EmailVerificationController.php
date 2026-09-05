<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EmailVerificationController extends Controller
{
    /**
     * POST /auth/email/verify/{id}/{hash} — public, authenticated by the signed URL.
     */
    public function verify(Request $request, string $id, string $hash): Response
    {
        /** @var User $user */
        $user = User::query()->findOrFail($id);

        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw new ApiException('El enlace de verificación no es válido.', 'INVALID_VERIFICATION_LINK', 403);
        }

        if ($user->hasVerifiedEmail()) {
            throw new ApiException('El correo ya estaba verificado.', 'EMAIL_ALREADY_VERIFIED', 409);
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return response()->noContent();
    }

    /**
     * POST /auth/email/resend — authenticated.
     */
    public function resend(Request $request): Response
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            throw new ApiException('El correo ya estaba verificado.', 'EMAIL_ALREADY_VERIFIED', 409);
        }

        $user->sendEmailVerificationNotification();

        return response()->noContent(202);
    }
}
