<?php

namespace Laravel\Reverb\Servers\Reverb\Contracts;

use React\EventLoop\LoopInterface;

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
    public function publish(array $payload): void;
}
