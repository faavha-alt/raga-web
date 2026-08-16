<?php

namespace App\Providers;

use Anthropic\Client as AnthropicClient;
use App\Http\Responses\OauthAuthorizationViewResponse;
use App\Services\Ai\AiCoachService;
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

        $this->app->singleton(AnthropicClient::class, fn () => new AnthropicClient(
            apiKey: config('services.anthropic.api_key'),
        ));

        $this->app->when(AiCoachService::class)
            ->needs('$model')
            ->give(fn () => config('services.anthropic.model'));
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
