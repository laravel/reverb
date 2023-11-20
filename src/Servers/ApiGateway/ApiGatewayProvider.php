<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Event;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Managers\ArrayChannelConnectionManager;
use Laravel\Reverb\Managers\ArrayChannelManager;
use Laravel\Reverb\Managers\ArrayConnectionManager;

class ApiGatewayProvider extends ServerProvider
{
    public function __construct(protected Application $app, protected array $config)
    {
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
            Event::dispatch($this->app->make(ApplicationProvider::class)
                ->findById($appId), [
                    'event' => $request->name,
                    'channel' => $request->channel,
                    'data' => $request->data,
                ]);

            return new JsonResponse((object) []);
        });
    }

    /**
     * Build the connection manager for the server.
     */
    public function buildConnectionManager(): ConnectionManager
    {
        return new ArrayConnectionManager;
    }

    /**
     * Build the channel manager for the server.
     */
    public function buildChannelManager(): ChannelManager
    {
        return new ArrayChannelManager;
    }

    /**
     * Build the channel manager for the server.
     */
    public function buildChannelConnectionManager(): ChannelConnectionManager
    {
        return new ArrayChannelConnectionManager;
    }
}
