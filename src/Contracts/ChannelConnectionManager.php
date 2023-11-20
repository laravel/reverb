<?php

namespace Laravel\Reverb\Contracts;

interface ChannelConnectionManager
{
    /**
     * Add a connection.
     */
    public function add(Connection $connection): void;

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void;

    /**
     * Get all the connections.
     */
    public function all(): array;

    /**
     * Flush the channel connection manager.
     */
    public function flush(): void;
}
