<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Postal\RandomLetterQuota;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RandomQuotaController extends Controller
{
    /**
     * GET /api/v1/random/quota — what the sender has left, and why they might
     * not be able to send (docs/api/botella-al-mar.md).
     */
    public function __invoke(Request $request, RandomLetterQuota $quota): JsonResponse
    {
        return response()->json(['data' => $quota->snapshot($request->user())]);
    }
}
