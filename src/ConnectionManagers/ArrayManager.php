<?php

namespace Reverb\ConnectionManagers;

use Reverb\Connection;
use Reverb\Contracts\ConnectionManager;
use SplObjectStorage;
use Traversable;

class ArrayManager implements ConnectionManager
{
    protected $connections;

    public function __construct()
    {
        $this->connections = new SplObjectStorage;
    }

    /**
     * Add a connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function add(Connection $connection): void
    {
        $this->connections->attach($connection);
    }

    /**
     * Remove a connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function remove(Connection $connection): void
    {
        $this->connections->detach($connection);
    }

    /**
     * Get all connections.
     *
     * @return Traversable
     */
    public function all(): Traversable
    {
        return $this->connections;
    }
}
