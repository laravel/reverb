<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Managers\ConnectionManager;
use Laravel\Reverb\Output;

class PruneStaleConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     *
     * @param  \Laravel\Reverb\Managers\ConnectionManager  $connections
     * @param  \Laravel\Reverb\Contracts\ChannelManager  $channels
     * @return void
     */
    public function handle(ConnectionManager $connections, ChannelManager $channels)
    {
        Application::all()->each(function ($application) use ($connections, $channels) {
            $connections
                ->for($application)
                ->all()
                ->filter
                ->isStale()
                ->each(function ($connection) use ($connections, $channels) {
                    $connections->disconnect($connection->identifier());
                    $channels->unsubscribeFromAll($connection);
                    $connection->disconnect();

                    Output::info('Connection Pruned', $connection->id());
                });
        });
    }
}
