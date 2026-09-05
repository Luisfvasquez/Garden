<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Verifies an email/password pair for the login and mobile-token endpoints.
 *
 * Same error for a missing user and a wrong password so the endpoint never
 * confirms whether an address is registered.
 */
class CredentialChecker
{
    public function verify(string $email, string $password): User
    {
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            throw new ApiException('Email o contraseña incorrectos.', 'INVALID_CREDENTIALS', 422);
        }

        return match ($user->status) {
            UserStatus::Active => $user,
            // Signing back in reactivates a self-deactivated account (docs/api/auth.md).
            UserStatus::Deactivated => tap($user, static function (User $u): void {
                $u->forceFill([
                    'status' => UserStatus::Active,
                    'deactivated_at' => null,
                    'deletes_at' => null,
                ])->save();
            }),
            UserStatus::Suspended => throw new ApiException('Tu cuenta está suspendida.', 'ACCOUNT_SUSPENDED', 403),
            UserStatus::Deleted => throw new ApiException('Esta cuenta ya no está disponible.', 'ACCOUNT_INACTIVE', 403),
        };
    }
}
