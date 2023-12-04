<?php

namespace Laravel\Reverb\Contracts;

interface ConnectionManager
{
    /**
     * Add a new connection.
     */
    public function connect(Connection $connection): void;

    /**
     * Find a connection.
     */
    public function find(string $id): ?Connection;

    /**
     * Get all the connections.
     */
    public function all(): array;

    /**
     * Update the state of a connection.
     */
    public function update(Connection $connection): void;

    /**
     * Forget a connection.
     */
    public function forget(Connection $connection): void;

    /**
     * Flush all connections.
     */
    public function flush(): void;
}
