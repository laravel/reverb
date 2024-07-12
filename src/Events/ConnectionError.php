<?php

namespace Laravel\Reverb\Events;

use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Events\Concerns\Dispatchable;

class ConnectionError
{
    use Dispatchable;

    /**
     * Create a new event instance.
     */
    public function __construct(public Connection $connection, public \Exception $exception)
    {
        //
    }
}
