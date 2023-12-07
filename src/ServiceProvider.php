<?php

namespace Laravel\Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\NullLogger;

class ServiceProvider extends BaseServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/reverb.php' => config_path('reverb.php'),
        ]);

        $this->app->make(ServerManager::class)
            ->driver()
            ->boot();
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/reverb.php', 'reverb'
        );

        $this->app->instance(Logger::class, new NullLogger);

        $this->app->singleton(ServerManager::class);

        $this->initializeServer();
    }

    /**
     * Initialize the server.
     */
    public function initializeServer(): void
    {
        $server = $this->app->make(ServerManager::class);

        $server->register();

        $this->app->singleton(
            ChannelManager::class,
            fn () => $server->getChannelManager()
        );

        $this->app->bind(
            ChannelConnectionManager::class,
            fn () => $server->getChannelConnectionManager()
        );
    }
}
