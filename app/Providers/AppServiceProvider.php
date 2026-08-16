<?php

namespace App\Providers;

use App\Http\Responses\OauthAuthorizationViewResponse;
use App\Services\HealthData\HealthDataSource;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Contracts\AuthorizationViewResponse;
use Laravel\Passport\Passport;

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

        $this->app->bind(AuthorizationViewResponse::class, OauthAuthorizationViewResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Passport::tokensExpireIn(now()->addDay());
        Passport::refreshTokensExpireIn(now()->addDays(30));
    }
}
