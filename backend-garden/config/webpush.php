<?php

declare(strict_types=1);

/*
 * Web Push (VAPID). Keys are generated once with:
 *   php artisan tinker --execute="print_r(\Minishlink\WebPush\VAPID::createVapidKeys());"
 * and pinned in the environment. The public key is served at
 * GET /api/v1/push/vapid-public-key.
 */
return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:hola@evergarden.test'),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // A push row waits at most this long to be grouped with siblings of the
    // same type before it goes out on its own.
    'group_window_seconds' => (int) env('PUSH_GROUP_WINDOW_SECONDS', 120),
];
