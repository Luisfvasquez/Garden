<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ModerationServiceProvider;
use App\Providers\PostalServiceProvider;

return [
    AppServiceProvider::class,
    ModerationServiceProvider::class,
    PostalServiceProvider::class,
];
