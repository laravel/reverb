<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Application;
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
                ->hydrated()
                ->filter
                ->isInactive()
                ->each(function ($connection) use ($connections) {
                    $connection->ping();
                    $connections->syncConnection($connection);
                });
        });
    }
}
