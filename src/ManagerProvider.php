<?php

namespace Laravel\Reverb;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Laravel\Reverb\Contracts\ApplicationProvider;

class ManagerProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApplicationManager::class);

        $this->app->bind(
            ApplicationProvider::class,
            fn () => $this->app->make(ApplicationManager::class)->driver()
        );
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array<int, class-string>
     */
    public function provides(): array
    {
        return [ApplicationManager::class, ApplicationProvider::class];
    }
}
