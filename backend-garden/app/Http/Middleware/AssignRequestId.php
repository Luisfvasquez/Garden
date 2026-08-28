<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stamps every API request/response with a correlation id.
 *
 * The id is echoed in the `X-Request-Id` header and surfaces in error
 * payloads as `meta.request_id` (see docs/api/_convenciones.md).
 */
class AssignRequestId
{
    public const ATTRIBUTE = 'request_id';

    public const HEADER = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get(self::HEADER) ?: (string) Str::uuid();
        $request->attributes->set(self::ATTRIBUTE, $id);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }
}
