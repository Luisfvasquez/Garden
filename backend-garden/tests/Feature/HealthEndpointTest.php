<?php

declare(strict_types=1);

it('reports service status without authentication', function (): void {
    $response = $this->getJson('/api/v1/health');

    $response
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.services.database', 'up')
        ->assertJsonStructure(['data' => ['status', 'time', 'version', 'services']]);

    expect($response->headers->get('X-Request-Id'))->not->toBeEmpty();
});

it('stamps the response with the caller supplied request id', function (): void {
    $this->getJson('/api/v1/health', ['X-Request-Id' => 'trace-123'])
        ->assertHeader('X-Request-Id', 'trace-123');
});
