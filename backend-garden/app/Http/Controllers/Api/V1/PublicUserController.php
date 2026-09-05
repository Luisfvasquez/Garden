<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicUserResource;
use App\Models\User;

class PublicUserController extends Controller
{
    /**
     * GET /users/{postal_handle} — minimal public profile, exact handle only.
     */
    public function show(string $postalHandle): PublicUserResource
    {
        $user = User::query()
            ->where('postal_handle', $postalHandle)
            ->where('status', UserStatus::Active)
            ->firstOrFail();

        return new PublicUserResource($user);
    }
}
