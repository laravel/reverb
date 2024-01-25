<?php

namespace Laravel\Reverb\Pulse\Recorders;

use Carbon\CarbonImmutable;
use Illuminate\Config\Repository;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\Concerns\Sampling;
use Laravel\Reverb\Events\MessageReceived;
use Laravel\Reverb\Events\MessageSent;

class Messages
{
    use Sampling;

    /**
     * The events to listen for.
     *
     * @var list<class-string>
     */
    public array $listen = [
        MessageReceived::class,
        MessageSent::class,
    ];

    /**
     * Create a new recorder instance.
     */
    public function __construct(protected Pulse $pulse, protected Repository $config)
    {
        //
    }

    /**
     * Record the message.
     */
    public function record(MessageSent $event): void
    {
        if (! $this->shouldSample()) {
            return;
        }

        $this->pulse->lazy(function () use ($event) {
            $this->pulse->record(
                type: "reverb_messages:{$event->connection->app()->id()}",
                key: "reverb_messages:{$event->connection->app()->id()}",
                value: $timestamp = CarbonImmutable::now()->getTimestamp(),
                timestamp: $timestamp,
            )->count();
        });
    }
}
