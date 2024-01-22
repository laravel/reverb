<?php

namespace Laravel\Reverb\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Laravel\Reverb\Contracts\Connection;

class MessageSent
{
    use Dispatchable;

    public function __construct(public Connection $connection, public string $message)
    {
        //
    }
}
