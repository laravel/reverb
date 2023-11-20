<?php

namespace Laravel\Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Contracts\ServerProvider;
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

        $this->app->make(ServerProvider::class)->boot();
    }

    /**
     * Register any application services.
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/reverb.php', 'reverb'
        );

        $this->registerServer();
    }

    public function registerServer()
    {
        $this->app->singleton(ServerManager::class);
        $this->app->bind(
            ServerProvider::class,
            fn () => $this->app->make(ServerManager::class)->driver()
        );

        $server = $this->app->make(ServerProvider::class);

        $server->register();

        $this->app->singleton(
            ConnectionManager::class,
            fn () => $server->buildConnectionManager()
        );

        $this->app->singleton(
            ChannelManager::class,
            fn () => $server->buildChannelManager()
        );

        $this->app->bind(
            ChannelConnectionManager::class,
            fn () => $server->buildChannelConnectionManager()
        );

        $this->app->instance(Logger::class, new NullLogger);
    }
}
