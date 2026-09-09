<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * `Idempotency-Key` support (docs/api/_convenciones.md): replaying the same key
 * within 24 h returns the original response without repeating the effect. No
 * key → straight through.
 */
class HandleIdempotency
{
    private const TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key'));
        if ($key === '') {
            return $next($request);
        }

        $cacheKey = sprintf(
            'idem:%s:%s',
            $request->user()?->getAuthIdentifier() ?? 'guest',
            sha1($request->method().'|'.$request->path().'|'.$key),
        );

        /** @var array{status:int, body:string, content_type:string}|null $stored */
        $stored = Cache::get($cacheKey);
        if ($stored !== null) {
            return response($stored['body'], $stored['status'])
                ->header('Content-Type', $stored['content_type'])
                ->header('Idempotency-Replay', 'true');
        }

        $lock = Cache::lock($cacheKey.':lock', 30);
        if (! $lock->get()) {
            return ApiExceptionRenderer::render(
                new ApiException('Ya hay una solicitud idéntica en curso.', 'IDEMPOTENCY_IN_PROGRESS', 409),
                $request,
            );
        }

        try {
            $response = $next($request);

            if ($response->getStatusCode() < 300) {
                Cache::put($cacheKey, [
                    'status' => $response->getStatusCode(),
                    'body' => (string) $response->getContent(),
                    'content_type' => (string) $response->headers->get('Content-Type', 'application/json'),
                ], now()->addHours(self::TTL_HOURS));
            }

            return $response;
        } finally {
            $lock->release();
        }
    }
}
