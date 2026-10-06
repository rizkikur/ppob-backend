<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
| Semua route API domain di-include dari direktori routes/api/.
| Format response selalu menggunakan ApiResponse envelope.
|
| Versi API: v1 (prefix diterapkan di bootstrap/app.php atau RouteServiceProvider)
*/

// ─── Domain Routes ────────────────────────────────────────────────────────────

require __DIR__.'/api/auth.php';
require __DIR__.'/api/security.php';
require __DIR__.'/api/wallet.php';
require __DIR__.'/api/products.php';
require __DIR__.'/api/inquiry.php';
require __DIR__.'/api/transactions.php';
require __DIR__.'/api/webhooks.php';
require __DIR__.'/partner.php';

// ─── API v1 Prefix Group (OpenAPI Standard) ───────────────────────────────────

Route::prefix('api/v1')->group(function () {
    require __DIR__.'/api/auth.php';
    require __DIR__.'/api/security.php';
    require __DIR__.'/api/wallet.php';
    require __DIR__.'/api/products.php';
    require __DIR__.'/api/inquiry.php';
    require __DIR__.'/api/transactions.php';
    require __DIR__.'/api/webhooks.php';
    require __DIR__.'/partner.php';
});
