<?php

namespace Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use InvalidArgumentException;
use Reverb\Console\Commands\RunServer;
use Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Reverb\Managers\ChannelManager;
use Reverb\Managers\ConnectionManager;
use Reverb\Servers\ApiGateway\ServiceProvider as ApiGatewayServiceProvider;
use Reverb\Servers\Ratchet\ServiceProvider as RatchetServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunServer::class,
            ]);
        }

        $this->publishes([
            __DIR__.'/../config/reverb.php' => config_path('reverb.php'),
        ]);
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
                    $config['connection_cache']['store'],
                    $config['connection_cache']['prefix']
                ),
            );
        });

        $this->app->singleton(ChannelManagerInterface::class, function ($app) use ($config) {
            return new ChannelManager(
                $app['cache']->store(
                    $config['channel_cache']['store']
                ),
                $app->make(ConnectionManagerInterface::class),
                $config['channel_cache']['prefix']
            );
        });

        $this->app->register(
            $this->getServerProvider($config['default'])
        );
    }

    /**
     * Register the server provider.
     *
     * @param  string  $server
     * @return void
     *
     * @throws \InvalidArgumentException
     */
    protected function getServerProvider($name)
    {
        return match ($name) {
            'ratchet' => RatchetServiceProvider::class,
            'api_gateway' => ApiGatewayServiceProvider::class,
            default => throw new InvalidArgumentException("Server provider [{$name}] is not supported."),
        };
    }
}
