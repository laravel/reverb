<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Loggers\Log;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

class PruneStaleConnections
{
    use Dispatchable;

    protected function getDisconnectString($connection): string
    {
        return '{"event":"pusher:error","data":"{\"code\":4201,\"message\":\"Pong reply not received in time\"}"}';
    }

    /**
     * Execute the job.
     */
    public function handle(ChannelManager $channels): void
    {
        Log::info('Pruning Stale Connections');

        app(ApplicationProvider::class)
            ->all()
            ->each(function ($application) use ($channels) {
                foreach ($channels->for($application)->connections() as $connection) {
                    if (! $connection->isStale()) {
                        continue;
                    }

                    $connection->send($this->getDisconnectString($connection));

                    $channels
                        ->for($connection->app())
                        ->unsubscribeFromAll($connection->connection());

                    $connection->disconnect();

                    Log::info('Connection Pruned', $connection->id());
                }
            });
    }
}
