<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\UserStatus;
use App\Models\User;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Server;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Shapes the generated OpenAPI document (docs/api/openapi.json).
 *
 * The written contract in `docs/api/` stays the source of truth (CLAUDE.md);
 * this document is generated from the code so the two can be compared, and it
 * is what `openapi-typescript` turns into the front-end's types.
 */
class ApiDocsServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        /*
         * Who may read /docs/api outside local. Scramble's RestrictedDocsAccess
         * lets `local` through and otherwise asks this gate; with no gate
         * defined it 403s for everyone, staff included. Same bar as the Filament
         * panel — staff, and only while the account is active (launch
         * checklist: "/docs protegido").
         */
        Gate::define(
            'viewApiDocs',
            fn (User $user) => $user->isStaff() && $user->status === UserStatus::Active,
        );

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            // A RELATIVE server. Scramble's `servers` config runs through the
            // `url()` helper, which would bake whoever generated the file's
            // APP_URL (http://localhost:8000) into a committed artifact. The
            // client resolves against its own origin, exactly like VITE_API_URL.
            $openApi->servers = [Server::make('/api/v1')];

            return $openApi;
        });
    }
}
