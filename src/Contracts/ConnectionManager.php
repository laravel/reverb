<?php

namespace Reverb\Contracts;

use Reverb\Contracts\Connection;
use Traversable;

interface ConnectionManager
{
    /**
     * Add a connection.
     *
     * @param  \Reverb\Contracts\Connection  $connection
     * @return void
     */
    public function connect(Connection $connection): Connection;

    /**
     * Remove a connection.
     *
     * @param  string  $identifier
     * @return void
     */
    public function disconnect(Connection $connection): void;

    /**
     * Get all connections.
     *
     * @return \Traversable
     */
    public function all(): Traversable;

    /**
     * Get a connection by its identifier.
     *
     * @param  string  $identifier
     * @return \Reverb\Contracts\Connection  $connection
     */
    public function get(string $identifier): ?Connection;
}
