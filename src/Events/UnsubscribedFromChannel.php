<?php

namespace Laravel\Reverb\Events;

use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\Concerns\Dispatchable;

class UnsubscribedFromChannel
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Connection $connection,
        public string $channelName,
    ) {
        //
    }
}
