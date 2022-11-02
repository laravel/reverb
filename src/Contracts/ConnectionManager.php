<?php

namespace Reverb\Contracts;

use Reverb\Connection;
use Traversable;

interface ConnectionManager
{
    /**
     * Add a connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function add(Connection $connection): void;

    /**
     * Remove a connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function remove(Connection $connection): void;

    /**
     * Get all connections.
     *
     * @return Traversable
     */
    public function all(): Traversable;
}
