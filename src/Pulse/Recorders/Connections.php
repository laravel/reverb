<?php

namespace Laravel\Reverb\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Broadcast;
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

        $connections = Broadcast::driver()
            ->getPusher()
            ->get('/connections')
            ->connections;

        $this->pulse->lazy(function () use ($connections) {
            $this->pulse->record(
                type: 'reverb_connections',
                key: 'reverb_connections',
                value: $connections,
                timestamp: CarbonImmutable::now()->getTimestamp(),
            )->avg()->max()->count();
        });
    }
}
