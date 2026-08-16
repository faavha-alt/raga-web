<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AiSettingsController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GarminConnectionController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\Mcp\McpTransportController;
use App\Http\Controllers\Oauth\ClientRegistrationController;
use App\Http\Controllers\Oauth\ResourceMetadataController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\RunningController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TrailController;
use App\Http\Controllers\TrainingController;
use App\Services\Analytics\RelationshipCatalog;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/.well-known/oauth-authorization-server', [ResourceMetadataController::class, 'authorizationServer']);
Route::get('/.well-known/oauth-protected-resource', [ResourceMetadataController::class, 'protectedResource']);
Route::post('/oauth/register', [ClientRegistrationController::class, 'store']);
Route::post('/mcp', [McpTransportController::class, 'handle'])->middleware('mcp.auth');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/training', [TrainingController::class, 'index'])->name('training');
    Route::get('/training/volume', [TrainingController::class, 'volume'])->name('training.volume');
    Route::get('/training/load', [TrainingController::class, 'load'])->name('training.load');
    Route::get('/training/calendar', [TrainingController::class, 'calendar'])->name('training.calendar');
    Route::get('/training/distribution', [TrainingController::class, 'distribution'])->name('training.distribution');
    Route::get('/running', [RunningController::class, 'index'])->name('running');
    Route::get('/running/distance', [RunningController::class, 'distance'])->name('running.distance');
    Route::get('/running/pace', [RunningController::class, 'pace'])->name('running.pace');
    Route::get('/running/records', [RunningController::class, 'records'])->name('running.records');
    Route::get('/trail', [TrailController::class, 'index'])->name('trail');
    Route::get('/trail/routes', [TrailController::class, 'routes'])->name('trail.routes');
    Route::get('/trail/{workout}', [TrailController::class, 'show'])->name('trail.show');
    Route::get('/activities', [ActivityController::class, 'index'])->name('activities');
    Route::get('/activities/{workout}', [ActivityController::class, 'show'])->name('activities.show');
    Route::get('/health', [HealthController::class, 'overview'])->name('health');
    Route::get('/health/heart', [HealthController::class, 'heart'])->name('health.heart');
    Route::get('/health/stress', [HealthController::class, 'stress'])->name('health.stress');
    Route::get('/health/body-battery', [HealthController::class, 'bodyBattery'])->name('health.body_battery');
    Route::get('/health/daily-metrics', [HealthController::class, 'dailyMetrics'])->name('health.daily_metrics');
    Route::get('/recovery', [RecoveryController::class, 'index'])->name('recovery');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/health-trends', [AnalyticsController::class, 'healthTrends'])->name('analytics.health_trends');
    Route::get('/analytics/training-trends', [AnalyticsController::class, 'trainingTrends'])->name('analytics.training_trends');
    Route::get('/analytics/{pair}', [AnalyticsController::class, 'relationship'])
        ->whereIn('pair', (new RelationshipCatalog)->slugs())
        ->name('analytics.relationship');
    Route::get('/ai', [AiController::class, 'index'])->name('ai');
    Route::post('/ai/messages', [AiController::class, 'sendMessage'])->name('ai.messages.store');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings');

    Route::prefix('settings/garmin')->name('settings.garmin.')->group(function () {
        Route::get('/', [GarminConnectionController::class, 'show'])->name('show');
        Route::post('/connect', [GarminConnectionController::class, 'connect'])->name('connect');
        Route::post('/sync', [GarminConnectionController::class, 'sync'])->name('sync');
        Route::post('/disconnect', [GarminConnectionController::class, 'disconnect'])->name('disconnect');
    });

    Route::prefix('settings/ai')->name('settings.ai.')->group(function () {
        Route::get('/', [AiSettingsController::class, 'show'])->name('show');
        Route::post('/', [AiSettingsController::class, 'update'])->name('update');
        Route::delete('/', [AiSettingsController::class, 'destroy'])->name('destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
