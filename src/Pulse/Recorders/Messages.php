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
        [$timestamp, $id] = [
            CarbonImmutable::now()->getTimestamp(),
            $event->connection->app()->id(),
        ];

        $this->pulse->lazy(function () use ($timestamp, $id) {
            if (! $this->shouldSample()) {
                return;
            }

            $this->pulse->record(
                type: "reverb_message:{$id}",
                key: 'received',
                value: $timestamp,
                timestamp: $timestamp,
            )->count()->onlyBuckets();
        });
    }
}
