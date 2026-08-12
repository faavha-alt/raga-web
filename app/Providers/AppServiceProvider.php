<?php

namespace App\Providers;

use App\Services\HealthData\HealthDataSource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(HealthDataSource::class, function ($app) {
            $key = config('health.source');
            $class = config("health.sources.{$key}");

            return $app->make($class);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
