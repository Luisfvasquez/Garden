<?php

declare(strict_types=1);

/*
 * docs/api/_convenciones.md: credentials on, explicit origins (never `*`).
 */
return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(
        ',',
        (string) env('FRONTEND_ORIGINS', 'http://localhost:5173,http://127.0.0.1:5173')
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['X-Request-Id', 'Retry-After'],

    'max_age' => 0,

    'supports_credentials' => true,

];
