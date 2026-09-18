<?php

declare(strict_types=1);

use App\Providers\ApiDocsServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\ModerationServiceProvider;
use App\Providers\PostalServiceProvider;
use App\Providers\PushServiceProvider;

return [
    AppServiceProvider::class,
    ApiDocsServiceProvider::class,
    AdminPanelProvider::class,
    ModerationServiceProvider::class,
    PostalServiceProvider::class,
    PushServiceProvider::class,
];
