<?php

declare(strict_types=1);

use App\Exceptions\ApiException;
use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\AssignRequestId;
use App\Http\Middleware\EnsureEmailIsVerified;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\HandleIdempotency;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Reverb. `auth:sanctum` so the channel handshake works for both the PWA
    // (session cookie) and mobile (Bearer token) — the default `web` guard
    // would only serve the first. ADR-0005: the only real-time channel is the
    // Doll chat.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['api', 'auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // SPA cookie auth for the PWA; mobile clients still use Bearer tokens.
        $middleware->statefulApi();

        $middleware->api(append: [
            AssignRequestId::class,
        ]);

        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'idempotency' => HandleIdempotency::class,
            'feature' => EnsureFeatureEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontReport(ApiException::class);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return ApiExceptionRenderer::render($e, $request);
            }

            return null;
        });
    })->create();
