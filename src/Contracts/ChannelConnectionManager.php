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
     * Find a connection by its identifier.
     */
    public function find(Connection $connection): ?ChannelConnection;

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
