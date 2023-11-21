<?php

namespace Laravel\Reverb\Contracts;

use Laravel\Reverb\Servers\Reverb\ChannelConnection;

interface ChannelConnectionManager
{
    /**
     * Add a connection.
     */
    public function add(Connection $connection, array $data): void;

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void;

    /**
     * Find a connection in the set.
     */
    public function find(Connection $connection): ?ChannelConnection;

    /**
     * Find a connection in the set by its identifier.
     */
    public function findById(string $identifier): ?ChannelConnection;

    /**
     * Get all the connections.
     *
     * @return array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    public function all(): array;

    /**
     * Flush the channel connection manager.
     */
    public function flush(): void;
}
