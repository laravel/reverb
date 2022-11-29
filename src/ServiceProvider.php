<?php

namespace Laravel\Reverb;

use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use InvalidArgumentException;
use Laravel\Reverb\Console\Commands\StartServer;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\StandardLogger;
use Laravel\Reverb\Managers\ChannelManager;
use Laravel\Reverb\Servers\ApiGateway\ServiceProvider as ApiGatewayServiceProvider;
use Laravel\Reverb\Servers\Ratchet\ServiceProvider as RatchetServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                StartServer::class,
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

        $this->app->singleton(ChannelManagerInterface::class, function ($app) use ($config) {
            return new ChannelManager(
                $app['cache']->store(
                    $config['channel_cache']['store']
                ),
                $config['channel_cache']['prefix']
            );
        });

        $this->app->instance(Logger::class, new StandardLogger);

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
