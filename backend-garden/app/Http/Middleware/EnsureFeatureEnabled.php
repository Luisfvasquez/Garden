<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\ApiException;
use App\Services\FeatureFlags;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a route behind a feature flag (../CLAUDE.md rule 5). A disabled module
 * responds 404 — the client learns nothing about what is coming.
 *
 * Usage: `->middleware('feature:schedules')`.
 */
class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureFlags $flags) {}

    public function handle(Request $request, Closure $next, string $flag): Response
    {
        if (! $this->flags->enabled($flag)) {
            throw new ApiException('Recurso no disponible.', 'NOT_FOUND', 404);
        }

        return $next($request);
    }
}
