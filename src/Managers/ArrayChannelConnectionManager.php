<?php

namespace Laravel\Reverb\Managers;

use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\Connection;

class ArrayChannelConnectionManager implements ChannelConnectionManager
{
    /**
     * Connection store.
     *
     * @var array<string, array<string, \Laravel\Reverb\Connection>>
     */
    protected $connections = [];

    /**
     * Add a connection.
     */
    public function add(Connection $connection): void
    {
        $this->connections[$connection->identifier()] = $connection;
    }

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void
    {
        unset($this->connections[$connection->identifier()]);
    }

    /**
     * Get all the connections.
     */
    public function all(): array
    {
        return $this->connections;
    }

    /**
     * Flush the channel connection manager.
     */
    public function flush(): void
    {
        $this->connections = [];
    }
}
