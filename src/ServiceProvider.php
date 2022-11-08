<?php

namespace Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Reverb\Console\Commands\RunServer;
use Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Reverb\Managers\ChannelManager;
use Reverb\Managers\ConnectionManager;

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

        $config = $this->app['config']['reverb'];

        $this->app->singleton(ConnectionManagerInterface::class, function ($app) use ($config) {
            return new ConnectionManager(
                $app['cache']->store(
                    $config['connection_cache']
                ),
            );
        });

        $this->app->singleton(ChannelManagerInterface::class, function ($app) use ($config) {
            return new ChannelManager(
                $app['cache']->store(
                    $config['channel_cache']
                ),
                $app->make(ConnectionManagerInterface::class)
            );
        });
    }
}
