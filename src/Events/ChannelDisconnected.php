<?php

namespace Laravel\Reverb\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Reverb\Contracts\Connection;

class ChannelDisconnected
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public Connection $connection, public string $channel)
    {
        //
    }
}
