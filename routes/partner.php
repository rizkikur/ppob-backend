<?php

use App\Domain\Partner\Http\Controllers\PartnerController;
use App\Domain\Partner\Http\Middleware\PartnerAuthMiddleware;
use App\Domain\Shared\Middleware\IdempotencyMiddleware;
use Illuminate\Support\Facades\Route;

// Semua endpoint partner dilindungi PartnerAuthMiddleware (API Key + HMAC)
Route::middleware([PartnerAuthMiddleware::class])->prefix('partner')->group(function () {
    Route::get('/balance', [PartnerController::class, 'balance']);
    Route::get('/products', [PartnerController::class, 'products']);
    Route::post('/transactions', [PartnerController::class, 'createTransaction'])
        ->middleware(IdempotencyMiddleware::class);
    Route::get('/transactions/{partnerRef}', [PartnerController::class, 'getTransaction']);
});
