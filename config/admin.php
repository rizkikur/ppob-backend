<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Path Prefix
    |--------------------------------------------------------------------------
    |
    | Prefix path URL untuk mengakses panel Web Admin Backoffice.
    | Default: 'office' (misal: /office/login, /office/dashboard).
    | Anda dapat mengubahnya kapan saja melalui file .env (ADMIN_PATH_PREFIX).
    |
    */
    'prefix' => env('ADMIN_PATH_PREFIX', 'office'),

    /*
    |--------------------------------------------------------------------------
    | Admin Domain (Optional for Production Subdomain Isolation)
    |--------------------------------------------------------------------------
    |
    | Jika diisi (misal: panel.domain.com atau office.domain.com), rute admin
    | hanya akan merespons domain tersebut. Permintaan dari domain publik
    | (seperti api.domain.com atau docs.domain.com) akan menghasilkan 404.
    |
    */
    'domain' => env('ADMIN_DOMAIN', null),
];
