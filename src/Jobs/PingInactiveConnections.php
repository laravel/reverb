<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Loggers\Log;
use Laravel\Reverb\Protocols\Pusher\ConnectionRegistry;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\EventHandler;

class PingInactiveConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     */
    public function handle(ChannelManager $channels): void
    {
        Log::info('Pinging Inactive Connections');

        $pusher = new EventHandler($channels);

        $connections = app(ConnectionRegistry::class);

        app(ApplicationProvider::class)
            ->all()
            ->each(function ($application) use ($connections, $pusher) {
                foreach ($connections->for($application) as $connection) {
                    if ($connection->isActive()) {
                        continue;
                    }

                    $pusher->ping($connection);

                    Log::info('Connection Pinged', $connection->id());
                }
            });
    }
}
