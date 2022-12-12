<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Application;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\ConnectionManager;

class PingInactiveConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     *
     * @param  \Laravel\Reverb\Contracts\ConnectionManager  $connections
     * @return void
     */
    public function handle(ConnectionManager $connections)
    {
        Application::all()->each(function ($application) use ($connections) {
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
