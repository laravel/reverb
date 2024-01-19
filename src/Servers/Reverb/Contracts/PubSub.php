<?php

namespace Laravel\Reverb\Servers\Reverb\Contracts;

use React\EventLoop\LoopInterface;

interface PubSub
{
    /**
     * Subscribe to the publisher.
     */
    public function subscribe(LoopInterface $loop): void;

    /**
     * Publish a payload to the publisher.
     */
    public function publish(array $payload): void;
}
