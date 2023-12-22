<?php

namespace Laravel\Reverb\Protocols\Pusher\Managers;

use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;

class ArrayChannelConnectionManager implements ChannelConnectionManager
{
    protected string $name;

    /**
     * Connection store.
     *
     * @var array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    protected $connections = [];

    /**
     * The channel name.
     */
    public function for(string $name): ChannelConnectionManager
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Add a connection.
     */
    public function add(Connection $connection, array $data): void
    {
        $this->connections[$connection->id()] = new ChannelConnection($connection, $data);
    }

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void
    {
        unset($this->connections[$connection->id()]);
    }

    /**
     * Find a connection in the set.
     */
    public function find(Connection $connection): ?ChannelConnection
    {
        return $this->findById($connection->id());
    }

    /**
     * Find a connection in the set by its ID.
     */
    public function findById(string $id): ?ChannelConnection
    {
        return $this->connections[$id] ?? null;
    }

    /**
     * Get all the connections.
     *
     * @return array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    public function all(): array
    {
        return $this->connections;
    }

    /**
     * Determine whether any connections remain on the channel.
     */
    public function isEmpty(): bool
    {
        return empty($this->connections);
    }

    /**
     * Flush the channel connection manager.
     */
    public function flush(): void
    {
        $this->connections = [];
    }
}
