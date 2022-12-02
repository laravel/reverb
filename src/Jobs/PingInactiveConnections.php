<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Application;
use Laravel\Reverb\Managers\ConnectionManager;

class PingInactiveConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     *
     * @param  \Laravel\Reverb\Managers\ConnectionManager  $connections
     * @return void
     */
    public function handle(ConnectionManager $connections)
    {
        Application::all()->each(function ($application) use ($connections) {
            $connections
                ->for($application)
                ->all()
                ->filter
                ->isInactive()
                ->each
                ->ping();
        });
    }
}
