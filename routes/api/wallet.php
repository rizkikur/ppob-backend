<?php

use App\Domain\Shared\Middleware\IdempotencyMiddleware;
use App\Domain\Wallet\Http\Controllers\TopupController;
use App\Domain\Wallet\Http\Controllers\WalletController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/wallet', [WalletController::class, 'balance']);
    Route::get('/wallet/channels', [TopupController::class, 'channels']);
    Route::get('/wallet/mutations', [WalletController::class, 'mutations']);
    // Top-up memerlukan idempotency key
    Route::post('/wallet/topup', [TopupController::class, 'topup'])
        ->middleware(IdempotencyMiddleware::class);
});
