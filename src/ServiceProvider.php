<?php

namespace Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Reverb\ConnectionManagers\ArrayManager;
use Reverb\Console\Commands\RunServer;
use Reverb\Contracts\ConnectionManager;

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
            return new ArrayManager;
        });
    }
}
