<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Managers\ChannelManager;
use Laravel\Reverb\Managers\ConnectionManager;
use Laravel\Reverb\Servers\Ratchet\Console\Commands\StartServer;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                StartServer::class,
            ]);
        }
    }

    public function register()
    {
        $this->app->bind(
            ConnectionManagerInterface::class,
            fn ($app) => new ConnectionManager(
                $app['cache']->store('array')
            )
        );

        $this->app->bind(
            ChannelManagerInterface::class,
            fn ($app) => new ChannelManager(
                $app['cache']->store('array'),
                $app->make(ConnectionManagerInterface::class)
            )
        );
    }
}
