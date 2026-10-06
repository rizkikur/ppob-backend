<?php

use App\Providers\AppServiceProvider;
use App\Providers\DomainServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    DomainServiceProvider::class,
    SanctumServiceProvider::class,
];
