<?php

use App\Domain\Security\Http\Controllers\PinController;
use App\Domain\Shared\Middleware\PinTokenMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/security/pin', [PinController::class, 'setPin']);
    Route::put('/security/pin', [PinController::class, 'changePin'])->middleware(PinTokenMiddleware::class.':change_pin');
    Route::post('/security/pin/challenge', [PinController::class, 'challenge']);
    Route::post('/security/pin/verify', [PinController::class, 'verify']);
});
