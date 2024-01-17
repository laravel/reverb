<?php

namespace Laravel\Reverb\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Pulse\Events\SharedBeat;
use Laravel\Pulse\Pulse;

class Connections
{
    public string $listen = SharedBeat::class;

    public function __construct(protected Pulse $pulse, protected Repository $config)
    {
        //
    }

    public function record(SharedBeat $event): void
    {
        $channels = Broadcast::driver()
            ->getPusher()
            ->get('/channels', ['info' => 'subscription_count'])
            ->channels;

        $connections = collect($channels)
            ->map(fn ($channel) => $channel->subscription_count)
            ->sum();

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
