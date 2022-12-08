<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Output;

class PruneStaleConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     *
     * @param  \Laravel\Reverb\Contracts\ConnectionManager  $connections
     * @param  \Laravel\Reverb\Contracts\ChannelManager  $channels
     * @return void
     */
    public function handle(ConnectionManager $connections, ChannelManager $channels)
    {
        Application::all()->each(function ($application) use ($connections, $channels) {
            $connections
                ->for($application)
                ->hydrated()
                ->filter
                ->isStale()
                ->each(function ($connection) use ($connections, $channels, $application) {
                    $connection->send(json_encode([
                        'event' => 'pusher:error',
                        'data' => json_encode([
                            'code' => 4201,
                            'message' => 'Pong reply not received in time',
                        ]),
                    ]));
                    $connections->disconnect($connection->identifier());
                    $channels->for($application)->unsubscribeFromAll($connection);
                    $connection->disconnect();

                    Output::info('Connection Pruned', $connection->id());
                });
        });
    }
}
