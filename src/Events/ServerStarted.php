<?php

namespace Laravel\Reverb\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;

class ServerStarted
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct()
    {
        //
    }
}
