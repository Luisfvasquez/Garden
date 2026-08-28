<?php

declare(strict_types=1);
use Illuminate\Support\Facades\Route;

it('renders unknown routes in the contract error envelope', function (): void {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertJsonPath('error_code', 'NOT_FOUND')
        ->assertJsonStructure(['message', 'error_code', 'meta' => ['request_id']]);
});

it('rejects unauthenticated access to protected routes with a 401 envelope', function (): void {
    // A throwaway protected route exercised through the real middleware stack.
    Route::middleware('auth:sanctum')
        ->get('/api/v1/_probe', fn () => response()->json(['ok' => true]));

    $this->getJson('/api/v1/_probe')
        ->assertUnauthorized()
        ->assertJsonPath('error_code', 'UNAUTHENTICATED')
        ->assertJsonPath('meta.request_id', fn ($id) => is_string($id) && $id !== '');
});
