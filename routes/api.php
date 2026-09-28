<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LeadWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes (prefixed with /api)
|--------------------------------------------------------------------------
*/

// Public authentication endpoints (rate limited against brute force).
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
});

// Endpoints that require a Sanctum bearer token.
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/user', [AuthController::class, 'user'])->name('auth.user');

    Route::apiResource('leads', LeadController::class);
});

// Inbound webhook for external lead sources (HMAC-signed, no user token).
Route::post('/webhooks/leads', LeadWebhookController::class)
    ->middleware(['webhook.signature', 'throttle:60,1'])
    ->name('webhooks.leads');
