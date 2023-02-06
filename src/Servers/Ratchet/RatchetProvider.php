<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Managers\ChannelManager;
use Laravel\Reverb\Managers\ConnectionManager;
use Laravel\Reverb\Servers\Ratchet\Console\Commands\StartServer;

class RatchetProvider extends ServerProvider
{
    public function __construct(protected Application $app, protected array $config)
    {
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            Artisan::starting(function ($artisan) {
                $artisan->resolveCommands([
                    StartServer::class,
                ]);
            });
        }
    }

    /**
     * Build the connection manager for the server.
     */
    public function buildConnectionManager(): ConnectionManagerInterface
    {
        return new ConnectionManager(
            $this->app['cache']->store('array'),
            $this->config['connection_manager']['prefix'] ?? 'reverb'
        );
    }

    /**
     * Build the channel manager for the server.
     */
    public function buildChannelManager(): ChannelManagerInterface
    {
        return new ChannelManager(
            $this->app['cache']->store('array'),
            $this->app->make(ConnectionManagerInterface::class),
            $this->config['connection_manager']['prefix'] ?? 'reverb'
        );
    }
}
