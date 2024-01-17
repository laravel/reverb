<?php

namespace Laravel\Reverb\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Laravel\Pulse\Pulse;
use Laravel\Reverb\Events\MessageSent;

class Messages
{
    public string $listen = MessageSent::class;

    public function __construct(protected Pulse $pulse, protected Repository $config)
    {
        //
    }

    public function record(MessageSent $event): void
    {
        $this->pulse->lazy(function () {
            $this->pulse->record(
                type: 'reverb_messages',
                key: 'reverb_messages',
                value: $timestamp = CarbonImmutable::now()->getTimestamp(),
                timestamp: $timestamp,
            )->count();
        });
    }
}
