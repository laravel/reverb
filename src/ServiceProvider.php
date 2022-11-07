<?php

namespace Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Reverb\Console\Commands\RunServer;
use Reverb\Contracts\ChannelManager;
use Reverb\Contracts\ConnectionManager;
use Reverb\Managers\Channels\Collection as ChannelCollection;
use Reverb\Managers\Connections\Collection as ConnectionCollection;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunServer::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/reverb.php', 'reverb'
        );

        $this->app->singleton(ConnectionManager::class, function ($app) {
            // @TODO use the manager pattern here.
            return new ConnectionCollection;
        });

        $this->app->singleton(ChannelManager::class, function ($app) {
            // @TODO use the manager pattern here.
            return new ChannelCollection(
                $this->app->make(ConnectionManager::class)
            );
        });
    }
}
