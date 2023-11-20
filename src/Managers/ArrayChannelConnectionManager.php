<?php

namespace Laravel\Reverb\Managers;

use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Servers\Reverb\ChannelConnection;

class ArrayChannelConnectionManager implements ChannelConnectionManager
{
    /**
     * Connection store.
     *
     * @var array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    protected $connections = [];

    /**
     * Add a connection.
     */
    public function add(Connection $connection, array $data): void
    {
        $this->connections[$connection->identifier()] = new ChannelConnection($connection, $data);
    }

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void
    {
        unset($this->connections[$connection->identifier()]);
    }

    /**
     * Find a connection by its identifier.
     */
    public function find(Connection $connection): ?ChannelConnection
    {
        return $this->connections[$connection->identifier()] ?? null;
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
