<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Event;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Managers\ChannelManager;
use Laravel\Reverb\Managers\ConnectionManager;

class ServiceProvider extends BaseServiceProvider
{
    public function boot()
    {
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->job(new PruneStaleConnections)->everyMinute();
            $schedule->job(new PingInactiveConnections)->everyMinute();
        });
    }

    public function register()
    {
        $config = $this->app['config']['reverb']['servers']['api_gateway'];

        Route::post('/apps/{appId}/events', function (Request $request, $appId) {
            Event::dispatch(Application::findById($appId), [
                'event' => $request->name,
                'channel' => $request->channel,
                'data' => $request->data,
            ]);

            return new JsonResponse((object) []);
        });

        $this->app->bind(
            ConnectionManagerInterface::class,
            fn ($app) => new ConnectionManager(
                $app['cache']->store(
                    $config['connection_manager']['store']
                ),
                $config['connection_manager']['prefix']
            )
        );

        $this->app->bind(
            ChannelManagerInterface::class,
            fn ($app) => new ChannelManager(
                $app['cache']->store(
                    $config['connection_manager']['store']
                ),
                $app->make(ConnectionManagerInterface::class),
                $config['connection_manager']['prefix']
            )
        );
    }
}
