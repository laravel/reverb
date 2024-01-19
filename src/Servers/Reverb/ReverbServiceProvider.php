<?php

namespace Laravel\Reverb\Servers\Reverb;

use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Servers\Reverb\Console\Commands\RestartServer;
use Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSub;

class ReverbServiceProvider extends ServerProvider
{
    /**
     * Indicates whether the Reverb server should publish events.
     *
     * @var bool
     */
    protected $publishesEvents;

    /**
     * Create a new Reverb server provider instance.
     */
    public function __construct(protected Application $app, protected array $config)
    {
        $this->publishesEvents = (bool) $this->config['scaling']['enabled'] ?? false;
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PubSub::class, fn ($app) => new RedisPubSub($this->config['scaling']['channel'] ?? 'reverb')
        );
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
                    RestartServer::class,
                ]);
            });
        }
    }

    /**
     * Enable publishing of events.
     */
    public function withPublishing(): void
    {
        $this->publishesEvents = true;
    }

    /**
     * Determine whether the server should publish events.
     */
    public function shouldPublishEvents(): bool
    {
        return $this->publishesEvents;
    }
}
