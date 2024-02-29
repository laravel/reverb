<?php

namespace Laravel\Reverb\Servers\Reverb\Contracts;

use React\EventLoop\LoopInterface;
use React\Promise\Promise;

interface PubSubProvider
{
    /**
     * Connect to the publisher.
     */
    public function connect(LoopInterface $loop): void;

    /**
     * Subscribe to the publisher.
     */
    public function subscribe(): void;

    /**
     * Publish a payload to the publisher.
     */
    public function publish(array $payload): Promise;

    /**
     * Listen for a specific event.
     */
    public function on(string $event, callable $callback): void;
}
