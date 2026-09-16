<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\DollProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * An unverified profile is invisible to everyone but its owner — the directory
 * never shows a Doll before staff verify them (docs/api/dolls.md).
 */
class DollProfilePolicy
{
    public function view(User $user, DollProfile $profile): Response
    {
        if ($profile->isVerified()) {
            return Response::allow();
        }

        return $user->id === $profile->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, DollProfile $profile): Response
    {
        return $user->id === $profile->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
