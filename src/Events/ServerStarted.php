<?php

namespace Laravel\Reverb\Events;

use Laravel\Reverb\Events\Concerns\Dispatchable;

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
