<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Events\ConnectionPruned;
use Laravel\Reverb\Loggers\Log;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\ConnectionRegistry;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

class PruneStaleConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     */
    public function handle(ChannelManager $channels): void
    {
        Log::info('Pruning Stale Connections');

        $connections = app(ConnectionRegistry::class);

        app(ApplicationProvider::class)
            ->all()
            ->each(function ($application) use ($channels, $connections) {
                foreach ($connections->for($application) as $connection) {
                    if (! $connection->isStale()) {
                        continue;
                    }

                    $connection->send(json_encode([
                        'event' => 'pusher:error',
                        'data' => json_encode([
                            'code' => 4201,
                            'message' => 'Pong reply not received in time',
                        ]),
                    ]));

                    $connections->remove($connection);

                    $channels
                        ->for($connection->app())
                        ->unsubscribeFromAll($connection);

                    $connection->disconnect();

                    Log::info('Connection Pruned', $connection->id());

                    ConnectionPruned::dispatch(new ChannelConnection($connection));
                }
            });
    }
}
