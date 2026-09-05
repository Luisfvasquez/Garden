<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\User;
use Illuminate\Http\Request;

class PostalHandleController extends Controller
{
    private const COOLDOWN_DAYS = 30;

    public function rotate(Request $request): MeResource
    {
        $user = $request->user();

        $rotatedAt = $user->postal_handle_rotated_at;
        if ($rotatedAt !== null && $rotatedAt->gt(now()->subDays(self::COOLDOWN_DAYS))) {
            $retryAt = $rotatedAt->copy()->addDays(self::COOLDOWN_DAYS);

            throw (new ApiException(
                'Solo puedes cambiar tu identificador postal una vez cada 30 días.',
                'HANDLE_ROTATION_TOO_SOON',
                429,
            ))->withMeta(['retry_at' => $retryAt->toIso8601ZuluString()]);
        }

        $user->forceFill([
            'postal_handle' => User::generatePostalHandle($user->name),
            'postal_handle_rotated_at' => now(),
        ])->save();

        return new MeResource($user);
    }
}
