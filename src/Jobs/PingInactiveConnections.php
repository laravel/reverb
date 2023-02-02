<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Contracts\ApplicationsProvider;
use Laravel\Reverb\Contracts\ConnectionManager;

class PingInactiveConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     */
    public function handle(ConnectionManager $connections): void
    {
        app(ApplicationsProvider::class)
            ->all()
            ->each(function ($application) use ($connections) {
                $connections
                    ->for($application)
                    ->all()
                    ->each(function ($connection) use ($connections) {
                        if ($connection->isActive()) {
                            return;
                        }

                        $connection->ping();
                        $connections->syncConnection($connection);
                    });
            });
    }
}
