<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Me\UpdateAvatarRequest;
use App\Http\Resources\MeResource;
use Illuminate\Support\Facades\Storage;

class AvatarController extends Controller
{
    public function store(UpdateAvatarRequest $request): MeResource
    {
        $user = $request->user();
        $previous = $user->avatar_path;

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->forceFill(['avatar_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return new MeResource($user);
    }
}
