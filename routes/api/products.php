<?php

use App\Domain\Product\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/products/categories', [ProductController::class, 'categories']);
    Route::get('/products', [ProductController::class, 'index']);
});
