<?php

namespace Laravel\Reverb\Servers\Swoole;

use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Managers\ChannelManager;
use Laravel\Reverb\Managers\ConnectionManager;
use Laravel\Reverb\Servers\Swoole\Console\Commands\StartServer as StartServerCommand;
use React\EventLoop\LoopInterface;

class SwooleProvider extends ServerProvider
{
    /**
     * Indicates whether the server should publish events.
     *
     * @var bool
     */
    protected $publishesEvents;

    public function __construct(protected Application $app, protected array $config)
    {
        $this->publishesEvents = (bool) $this->config['publish_events']['enabled'] ?? false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            Artisan::starting(function ($artisan) {
                $artisan->resolveCommands([
                    StartServerCommand::class,
                ]);
            });
        }
    }

    /**
     * Determine whether the server should publish events.
     */
    public function shouldPublishEvents(): bool
    {
        return $this->publishesEvents;
    }

    /**
     * Publish the given payload on the configured channel.
     */
    public function publish(array $payload): void
    {
        // TODO: Implement publish() method.
    }

    /**
     * Subscribe to the configured channel.
     */
    public function subscribe(LoopInterface $loop)
    {
        // Implement subscribe() method.
    }

    /**
     * Enable publishing of events.
     */
    public function withPublishing(): void
    {
        $this->publishesEvents = true;
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
