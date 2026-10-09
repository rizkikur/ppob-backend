<?php

use App\Domain\Shared\Http\Controllers\DocsController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DocsController::class, 'index'])->name('home');
Route::get('/docs', [DocsController::class, 'index'])->name('docs.index');
Route::get('/docs/openapi.yaml', [DocsController::class, 'spec'])->name('docs.spec');
Route::get('/docs/postman/collection', [DocsController::class, 'postmanCollection'])->name('docs.postman.collection');
Route::get('/docs/postman/environment', [DocsController::class, 'postmanEnvironment'])->name('docs.postman.environment');
