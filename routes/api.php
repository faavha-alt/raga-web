<?php

use App\Http\Controllers\Api\AuthTokenController;
use App\Http\Controllers\Api\McpController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/token', [AuthTokenController::class, 'store'])
    ->middleware('throttle:6,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/auth/token', [AuthTokenController::class, 'destroy']);

    Route::prefix('mcp')->name('api.mcp.')->group(function () {
        Route::get('/overview', [McpController::class, 'overview'])->name('overview');
        Route::get('/training', [McpController::class, 'training'])->name('training');
        Route::get('/recovery', [McpController::class, 'recovery'])->name('recovery');
        Route::get('/health', [McpController::class, 'health'])->name('health');
        Route::get('/running', [McpController::class, 'running'])->name('running');
        Route::get('/trail', [McpController::class, 'trail'])->name('trail');
    });
});
