<?php

use App\Domain\Shared\Middleware\IdempotencyMiddleware;
use App\Domain\Shared\Middleware\PinTokenMiddleware;
use App\Domain\Transaction\Http\Controllers\TransactionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/transactions', [TransactionController::class, 'index']);
    Route::get('/transactions/{id}', [TransactionController::class, 'show']);
    // Buat transaksi: wajib PIN token + idempotency key
    Route::post('/transactions', [TransactionController::class, 'store'])
        ->middleware([
            PinTokenMiddleware::class.':transaction',
            IdempotencyMiddleware::class,
        ]);
});
