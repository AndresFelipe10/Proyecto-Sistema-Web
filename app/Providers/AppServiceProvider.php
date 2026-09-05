<?php

namespace App\Providers;

use App\AI\Contracts\AiProviderInterface;
use App\AI\Providers\FakeAiProvider;
use App\AI\Providers\GeminiProvider;
use App\Services\Tenant\TenantManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantManager::class, function () {
            return new TenantManager();
        });

        $this->app->bind(AiProviderInterface::class, function () {
            if ($this->app->environment('testing') || config('ai.provider') === 'fake') {
                return new FakeAiProvider();
            }

            return new GeminiProvider();
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
