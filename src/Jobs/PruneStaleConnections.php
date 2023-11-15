<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Output;

class PruneStaleConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     */
    public function handle(ConnectionManager $connections): void
    {
        app(ApplicationProvider::class)
            ->all()
            ->each(function ($application) use ($connections) {
                collect($connections->for($application)->all())
                    ->each(function ($connection) {
                        if (! $connection->isStale()) {
                            return;
                        }

                        $connection->send(json_encode([
                            'event' => 'pusher:error',
                            'data' => json_encode([
                                'code' => 4201,
                                'message' => 'Pong reply not received in time',
                            ]),
                        ]));

                        $connection->disconnect();

                        Output::info('Connection Pruned', $connection->id());
                    });
            });
    }
}
