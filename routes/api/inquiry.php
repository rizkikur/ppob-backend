<?php

use App\Domain\Inquiry\Http\Controllers\InquiryController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/inquiry', [InquiryController::class, 'store']);
});
