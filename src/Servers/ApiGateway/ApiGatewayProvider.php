<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Pusher\EventDispatcher;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Managers\CacheChannelConnectionManager;
use Laravel\Reverb\Managers\CacheChannelManager;
use Laravel\Reverb\Managers\CacheConnectionManager;

class ApiGatewayProvider extends ServerProvider
{
    /**
     * Create a new API Gateway server provider instance.
     */
    public function __construct(protected Application $app, protected array $config)
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->job(new PruneStaleConnections)->everyMinute();
            $schedule->job(new PingInactiveConnections)->everyMinute();
        });
    }

    /**
     * Register any application services.
     */
    public function register(): void
    {
        Route::post('/apps/{appId}/events', function (Request $request, $appId) {
            EventDispatcher::dispatch($this->app->make(ApplicationProvider::class)
                ->findById($appId), [
                    'event' => $request->name,
                    'channel' => $request->channel,
                    'data' => $request->data,
                ]);

            return new JsonResponse((object) []);
        });

        $this->app->singleton(ConnectionManager::class, function () {
            return new CacheConnectionManager(
                $this->app['cache']->store(
                    $this->config['connection_manager']['store']
                ),
                $this->config['connection_manager']['prefix']
            );
        });
    }

    /**
     * Return the channel manager for the server.
     */
    public function getChannelManager(): ChannelManager
    {
        return new CacheChannelManager(
            $this->app['cache']->store(
                $this->config['connection_manager']['store']
            ),
            $this->config['connection_manager']['prefix']
        );
    }

    /**
     * Return the channel manager for the server.
     */
    public function getChannelConnectionManager(): ChannelConnectionManager
    {
        return new CacheChannelConnectionManager(
            $this->app['cache']->store(
                $this->config['connection_manager']['store']
            ),
            app(ConnectionManager::class),
            $this->config['connection_manager']['prefix']
        );
    }
}
