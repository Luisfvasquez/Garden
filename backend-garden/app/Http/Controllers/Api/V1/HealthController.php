<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * GET /api/v1/health — liveness probe. Public.
     */
    public function __invoke(): JsonResponse
    {
        $database = $this->check(static function (): bool {
            DB::connection()->select('select 1');

            return true;
        });

        $ok = $database;

        return new JsonResponse([
            'data' => [
                'status' => $ok ? 'ok' : 'degraded',
                'time' => now()->utc()->toIso8601ZuluString(),
                'version' => (string) config('app.version', 'dev'),
                'services' => [
                    'database' => $database ? 'up' : 'down',
                ],
            ],
        ], $ok ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return (bool) $probe();
        } catch (\Throwable) {
            return false;
        }
    }
}
