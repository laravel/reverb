<?php

namespace Laravel\Reverb;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Contracts\ApplicationsProvider;

class ManagerProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApplicationsManager::class);

        $this->app->bind(
            ApplicationsProvider::class,
            fn () => $this->app->make(ApplicationsManager::class)->driver()
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [ApplicationsManager::class, ApplicationsProvider::class];
    }
}
