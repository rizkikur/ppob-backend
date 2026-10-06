<?php

use App\Domain\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Endpoint publik — Phase 1 spec
Route::post('/auth/otp', [AuthController::class, 'sendOtp']);
Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/verify-register', [AuthController::class, 'verifyRegister']);
Route::post('/auth/login', [AuthController::class, 'login']);

// Endpoint publik — OpenAPI alias
Route::post('/auth/otp/request', [AuthController::class, 'sendOtp']);
Route::post('/auth/otp/verify', [AuthController::class, 'otpVerify']);

// Endpoint yang butuh autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
