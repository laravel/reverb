<?php

namespace Laravel\Reverb\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Reverb\Servers\Reverb\Connection;

class MessageSent
{
    use Dispatchable;

    public function __construct(public Connection $connection, public string $message)
    {
        //
    }
}
