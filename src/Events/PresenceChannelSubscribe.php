<?php

namespace Laravel\Reverb\Events;

use Laravel\Reverb\Contracts\Connection;
use Illuminate\Foundation\Events\Dispatchable;
class PresenceChannelSubscribe

{
    use Dispatchable;
    public function __construct(
        public string $channel,
        public Connection $connection
    ) {}
    


}