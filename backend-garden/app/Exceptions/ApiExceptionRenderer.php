<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\AssignRequestId;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Maps every uncaught throwable to the single error envelope defined in
 * docs/api/_convenciones.md: `message`, `error_code`, `errors`, `meta.request_id`.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e, Request $request): JsonResponse
    {
        [$status, $code, $message, $errors, $meta, $headers] = self::resolve($e, $request);

        $payload = [
            'message' => $message,
            'error_code' => $code,
        ];

        if ($errors !== []) {
            $payload['errors'] = $errors;
        }

        $payload['meta'] = [
            'request_id' => $request->attributes->get(AssignRequestId::ATTRIBUTE)
                ?? $request->headers->get(AssignRequestId::HEADER)
                ?? (string) Str::uuid(),
            ...$meta,
        ];

        return new JsonResponse($payload, $status, $headers);
    }

    /**
     * @return array{0:int,1:string,2:string,3:array<string,mixed>,4:array<string,mixed>,5:array<string,string>}
     */
    private static function resolve(Throwable $e, Request $request): array
    {
        return match (true) {
            $e instanceof ApiException => [
                $e->status, $e->errorCode, $e->getMessage(), $e->errors, $e->meta, [],
            ],
            $e instanceof ValidationException => [
                422, 'VALIDATION_FAILED', $e->getMessage(), $e->errors(), [], [],
            ],
            $e instanceof AuthenticationException => [
                401, 'UNAUTHENTICATED', 'No autenticado.', [], [], [],
            ],
            $e instanceof InvalidSignatureException => [
                403, 'INVALID_VERIFICATION_LINK', 'El enlace no es válido o ha caducado.', [], [], [],
            ],
            $e instanceof AuthorizationException => self::forStatus(
                $e->status() ?? 403,
                $e->status() === 404 ? 'Recurso no encontrado.' : 'No tienes permiso para esta acción.',
            ),
            $e instanceof AccessDeniedHttpException => [
                403, 'FORBIDDEN', 'No tienes permiso para esta acción.', [], [], [],
            ],
            $e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException => [
                404, 'NOT_FOUND', 'Recurso no encontrado.', [], [], [],
            ],
            $e instanceof MethodNotAllowedHttpException => [
                405, 'METHOD_NOT_ALLOWED', 'Método no permitido.', [], [], [],
            ],
            $e instanceof ThrottleRequestsException => [
                429, 'RATE_LIMITED', 'Demasiadas solicitudes. Inténtalo más tarde.', [], [],
                array_filter(['Retry-After' => $e->getHeaders()['Retry-After'] ?? null]),
            ],
            $e instanceof HttpExceptionInterface => self::forStatus(
                $e->getStatusCode(),
                $e->getMessage() ?: 'Error de solicitud.',
                $e->getHeaders(),
            ),
            default => [
                500,
                'SERVER_ERROR',
                config('app.debug') ? $e->getMessage() : 'Ha ocurrido un error inesperado.',
                [],
                config('app.debug') ? ['exception' => $e::class] : [],
                [],
            ],
        };
    }

    /**
     * @param  array<string, string>  $headers
     * @return array{0:int,1:string,2:string,3:array<string,mixed>,4:array<string,mixed>,5:array<string,string>}
     */
    private static function forStatus(int $status, string $message, array $headers = []): array
    {
        $code = match ($status) {
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            429 => 'RATE_LIMITED',
            default => 'HTTP_ERROR',
        };

        return [$status, $code, $message, [], [], $headers];
    }
}
