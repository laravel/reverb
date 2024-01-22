<?php

namespace Laravel\Reverb\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Config\Repository;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Sampling;

class Connections
{
    use Sampling;

    public string $listen = SharedBeat::class;

    public function __construct(protected Pulse $pulse, protected Repository $config)
    {
        //
    }

    public function record(SharedBeat $event): void
    {
        if ($event->time->second % 15 !== 0) {
            return;
        }

        if (! $this->shouldSample()) {
            return;
        }

        foreach (config('reverb.apps.apps') as $app) {
            $client = app(BroadcastManager::class)->pusher($app);
            $connections = $client->get('/connections')->connections;

            $this->pulse->lazy(function () use ($app, $connections) {
                $this->pulse->record(
                    type: "reverb_connections:{$app['app_id']}",
                    key: "reverb_connections:{$app['app_id']}",
                    value: $connections,
                    timestamp: CarbonImmutable::now()->getTimestamp(),
                )->avg()->max()->sum();
            });
        }
    }
}
