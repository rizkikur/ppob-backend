<?php

use App\Domain\Auth\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Endpoint publik — OTP (Dual-Key Throttled by IP & Phone)
Route::middleware('throttle:api-auth-otp')->group(function () {
    Route::post('/auth/otp', [AuthController::class, 'sendOtp']);
    Route::post('/auth/otp/request', [AuthController::class, 'sendOtp']);
});

// Endpoint publik — Registrasi & Login (Dual-Key Throttled by IP & Phone)
Route::middleware('throttle:api-auth-login')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/verify-register', [AuthController::class, 'verifyRegister']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/otp/verify', [AuthController::class, 'otpVerify']);
});

// Endpoint yang butuh autentikasi
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::post('/auth/fcm-token', [AuthController::class, 'updateFcmToken']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});
