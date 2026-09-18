<?php

declare(strict_types=1);

/**
 * Un invitado en una ruta protegida debe recibir 401, no un redirect.
 *
 * Este backend está desacoplado y no tiene `route('login')`. El redirect que
 * Laravel registra por defecto lo convertía en un 500 "Route [login] not
 * defined" para cualquier cliente que no mandara `Accept: application/json`:
 * un navegador siguiendo el enlace del PDF, `curl`, o un cliente móvil con
 * cabeceras por defecto. Se ve en bootstrap/app.php (`redirectGuestsTo`).
 */
it('devuelve 401 aunque el cliente no pida JSON', function (string $path): void {
    $this->get($path)
        ->assertUnauthorized()
        ->assertJsonPath('error_code', 'UNAUTHENTICATED');
})->with([
    '/api/v1/mailbox',
    '/api/v1/letters',
    '/api/v1/deliveries',
    '/api/v1/me',
]);

it('devuelve 401 en la descarga del PDF, que es una navegación del navegador', function (): void {
    // Sin Accept: application/json, exactamente como el <a href> del buzón.
    $this->get('/api/v1/mailbox/'.fake()->uuid().'/pdf')
        ->assertUnauthorized()
        ->assertJsonPath('error_code', 'UNAUTHENTICATED');
});

it('sigue devolviendo 401 cuando el cliente sí pide JSON', function (): void {
    $this->getJson('/api/v1/mailbox')
        ->assertUnauthorized()
        ->assertJsonPath('error_code', 'UNAUTHENTICATED');
});
