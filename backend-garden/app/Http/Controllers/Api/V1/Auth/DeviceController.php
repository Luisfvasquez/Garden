<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\DeviceResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DeviceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $devices = $request->user()
            ->tokens()
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get();

        return DeviceResource::collection($devices);
    }

    public function destroy(Request $request, string $id): Response
    {
        // Scoped to the caller: revoking someone else's token 404s, never 403.
        $request->user()->tokens()->whereKey($id)->firstOrFail()->delete();

        return response()->noContent();
    }
}
