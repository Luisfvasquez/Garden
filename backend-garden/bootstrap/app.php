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
use Illuminate\Support\Facades\Route;

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

        /*
         * Este backend está desacoplado: no hay login en HTML al que mandar a
         * nadie. Sin esto, Laravel registra por defecto un redirect a
         * `route('login')` y **cualquier petición sin `Accept: application/json`**
         * a una ruta protegida revienta con "Route [login] not defined" —
         * un 500 en lugar de un 401. Le pasa a un navegador siguiendo el enlace
         * de descarga del PDF, a `curl`, y a un cliente móvil con cabeceras por
         * defecto. Devolver null hace que se renderice el 401 de siempre.
         */
        $middleware->redirectGuestsTo(
            fn (Request $request): ?string => Route::has('login') ? route('login') : null,
        );

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
