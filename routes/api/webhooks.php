<?php

use App\Domain\Ppob\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Endpoint webhook — tidak pakai auth:sanctum, pakai validasi mTLS + HMAC sendiri
Route::post('/ppob/callback', [WebhookController::class, 'ppobCallback']);
Route::post('/wallet/callback', [WebhookController::class, 'walletCallback']);
