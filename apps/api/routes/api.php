<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('web');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware('web');
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/tokens', [AuthController::class, 'createToken']);
        Route::delete('/auth/tokens/{id}', [AuthController::class, 'revokeToken']);
        Route::get('/settings', [SettingsController::class, 'show']);
    });
});
