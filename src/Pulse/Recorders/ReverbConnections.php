<?php

namespace Laravel\Reverb\Pulse\Recorders;

use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Config\Repository;
use Laravel\Pulse\Events\IsolatedBeat;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Sampling;

class ReverbConnections
{
    use Sampling;

    /**
     * The events to listen for.
     *
     * @var class-string
     */
    public string $listen = IsolatedBeat::class;

    /**
     * Create a new recorder instance.
     */
    public function __construct(
        protected Pulse $pulse,
        protected BroadcastManager $broadcast,
        protected Repository $config,
    ) {
        //
    }

    /**
     * Record the connection count.
     */
    public function record(IsolatedBeat $event): void
    {
        if ($event->time->second % 15 !== 0) {
            return;
        }

        foreach ($this->config->get('reverb.apps.apps') as $app) {
            $connections = $this->broadcast->pusher($app)
                ->get('/connections')
                ->connections;

            $this->pulse->record(
                type: 'reverb_connections',
                key: $app['app_id'],
                value: $connections,
                timestamp: $event->time->getTimestamp(),
            )->avg()->max()->onlyBuckets();
        }
    }
}
