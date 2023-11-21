<?php

namespace Laravel\Reverb\Jobs;

use Illuminate\Foundation\Bus\Dispatchable;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager;

class PingInactiveConnections
{
    use Dispatchable;

    /**
     * Execute the job.
     */
    public function handle(ChannelManager $channels): void
    {
        app(ApplicationProvider::class)
            ->all()
            ->each(function ($application) use ($channels) {
                $channels->for($application)->connections()
                    ->each(function ($connection) {
                        if ($connection->isActive()) {
                            return;
                        }

                        $connection->ping();
                    });
            });
    }
}
