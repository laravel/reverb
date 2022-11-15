<?php

namespace Laravel\Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use InvalidArgumentException;
use Laravel\Reverb\Console\Commands\RunServer;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Managers\ChannelManager;
use Laravel\Reverb\Managers\ConnectionManager;
use Laravel\Reverb\Servers\ApiGateway\ServiceProvider as ApiGatewayServiceProvider;
use Laravel\Reverb\Servers\Ratchet\ServiceProvider as RatchetServiceProvider;

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
