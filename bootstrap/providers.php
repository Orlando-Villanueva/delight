<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PerformanceServiceProvider;
use App\Providers\RouteServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    PerformanceServiceProvider::class,
    RouteServiceProvider::class,
    TelescopeServiceProvider::class,
];
