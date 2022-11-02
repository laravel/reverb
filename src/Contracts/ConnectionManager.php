<?php

namespace Reverb\Contracts;

use Ratchet\ConnectionInterface;
use Traversable;

interface ConnectionManager
{
    /**
     * Add a connection.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return void
     */
    public function add(ConnectionInterface $connection): void;

    /**
     * Remove a connection.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return void
     */
    public function remove(ConnectionInterface $connection): void;

    /**
     * Get all connections.
     *
     * @return Traversable
     */
    public function all(): Traversable;
}
