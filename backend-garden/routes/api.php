<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
 * API version manifest. Each version lives in its own file so v1 and v2 can
 * coexist during a transition (docs/api/_convenciones.md).
 */
Route::prefix('v1')
    ->name('api.v1.')
    ->group(base_path('routes/api/v1.php'));
