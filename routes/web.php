<?php

use App\Http\Controllers\AiController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GarminConnectionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TrainingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/training', [TrainingController::class, 'index'])->name('training');
    Route::get('/health', [HealthController::class, 'index'])->name('health');
    Route::get('/ai', [AiController::class, 'index'])->name('ai');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::prefix('settings/garmin')->name('settings.garmin.')->group(function () {
        Route::get('/', [GarminConnectionController::class, 'show'])->name('show');
        Route::post('/connect', [GarminConnectionController::class, 'connect'])->name('connect');
        Route::post('/sync', [GarminConnectionController::class, 'sync'])->name('sync');
        Route::post('/disconnect', [GarminConnectionController::class, 'disconnect'])->name('disconnect');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
