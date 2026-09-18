<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The committed `docs/api/openapi.json` is a shared artifact: the front-end
 * generates its types from it (`npm run api:types`). A route added without
 * regenerating leaves the front blind to it, so that is what these tests guard.
 */
function openApiDocument(): array
{
    $path = base_path('../docs/api/openapi.json');

    expect(file_exists($path))->toBeTrue('Falta docs/api/openapi.json — corre `php artisan scramble:export --path=../docs/api/openapi.json`');

    return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
}

/**
 * Scramble camel-cases path parameter names (`{postal_handle}` → `{postalHandle}`).
 * That is cosmetic — a path parameter's name never reaches the URL, only its
 * position does — so routes are compared by shape, not by parameter spelling.
 */
function routeShape(string $path): string
{
    return (string) preg_replace('/\{[^}]+\}/', '{}', $path);
}

it('documents every api/v1 route', function (): void {
    $document = openApiDocument();
    $documented = array_map(routeShape(...), array_keys($document['paths']));

    $missing = [];

    foreach (Route::getRoutes() as $route) {
        $uri = $route->uri();

        if (! str_starts_with($uri, 'api/v1/')) {
            continue;
        }

        // The document strips the `api/v1` prefix — a single static
        // `api_path` include puts it in `servers` instead (config/scramble.php).
        $path = '/'.substr($uri, strlen('api/v1/'));

        if (! in_array(routeShape($path), $documented, true)) {
            $missing[] = $path;
        }
    }

    expect(array_values(array_unique($missing)))->toBe(
        [],
        'Rutas sin documentar. Regenera con `php artisan scramble:export --path=../docs/api/openapi.json`.',
    );
});

it('keeps the server relative, so no one bakes their APP_URL into it', function (): void {
    $document = openApiDocument();

    expect($document['servers'])->toBe([['url' => '/api/v1']]);
});

it('exposes the resource schemas the front-end types are generated from', function (): void {
    $schemas = array_keys(openApiDocument()['components']['schemas']);

    // A spot-check across modules: if these vanish, `src/types/contract.ts`
    // stops checking anything and drift goes silent again.
    expect($schemas)->toContain(
        'MeResource',
        'LetterResource',
        'DeliveryResource',
        'DollRequestResource',
        'DollChatMessageResource',
        'PartyResource',
    );
});

// --- /docs/api access (launch checklist: "/docs protegido") ------------------

it('serves the docs UI in local', function (): void {
    app()['env'] = 'local';

    $this->get('/docs/api')->assertOk();
});

it('denies the docs UI to a guest outside local', function (): void {
    app()['env'] = 'production';

    $this->get('/docs/api')->assertForbidden();
});

it('denies the docs UI to an ordinary client outside local', function (): void {
    app()['env'] = 'production';

    $this->actingAs(User::factory()->create(['role' => UserRole::Client]))
        ->get('/docs/api')
        ->assertForbidden();
});

it('denies the docs UI to suspended staff', function (): void {
    app()['env'] = 'production';

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Moderator,
        'status' => UserStatus::Suspended,
    ]))->get('/docs/api')->assertForbidden();
});

it('lets active staff read the docs UI outside local', function (): void {
    app()['env'] = 'production';

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Moderator,
        'status' => UserStatus::Active,
    ]))->get('/docs/api')->assertOk();
});
