<?php

namespace Laravel\Reverb\Channels;

use Laravel\Reverb\Contracts\Connection;

class CacheChannel extends Channel
{
    /**
     * Data from last event triggered.
     */
    protected array $event = [];

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, string $auth = null, string $data = null): void
    {
        parent::subscribe($connection, $auth, $data);

        if (! empty($this->event)) {
            $connection->send(
                json_encode($this->event)
            );
        }
    }

    /**
     * Send a message to all connections subscribed to the channel.
     */
    public function broadcast(array $payload, Connection $except = null): void
    {
        $this->event = $payload;

        parent::broadcast($payload, $except);
    }
}
