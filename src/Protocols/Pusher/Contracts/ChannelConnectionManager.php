<?php

namespace Laravel\Reverb\Protocols\Pusher\Contracts;

use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;

interface ChannelConnectionManager
{
    /**
     * The channel name.
     */
    public function for(string $name): ChannelConnectionManager;

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
     * Find a connection in the set by its ID.
     */
    public function findById(string $id): ?ChannelConnection;

    /**
     * Get all the connections.
     *
     * @return array<string, \Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection>
     */
    public function all(): array;

    /**
     * Determine whether any connections remain on the channel.
     */
    public function isEmpty(): bool;

    /**
     * Flush the channel connection manager.
     */
    public function flush(): void;
}
