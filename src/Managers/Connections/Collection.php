<?php

namespace Reverb\Managers\Connections;

use Illuminate\Support\Collection as IlluminateCollection;
use Reverb\Connection;
use Reverb\Contracts\ConnectionManager;
use Traversable;

class Collection implements ConnectionManager
{
    /**
     * The connections.
     *
     * @var \Illuminate\Support\Collection<\Reverb\Connection>
     */
    protected $connections;

    public function __construct()
    {
        $this->connections = new IlluminateCollection;
    }

    /**
     * Add a connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function connect(Connection $connection): Connection
    {
        $this->connections->put($connection->identifier(), $connection);

        return $connection;
    }

    /**
     * Remove a connection.
     *
     * @param  string  $identifier
     * @return void
     */
    public function disconnect(Connection $connection): void
    {
        $this->connections->forget($connection->identifier());
    }

    /**
     * Get all connections.
     *
     * @return \Traversable
     */
    public function all(): Traversable
    {
        return $this->connections;
    }

    /**
     * Get a connection by its identifier.
     *
     * @param  string  $identifier
     * @return \Reverb\Connection  $connection
     */
    public function get(string $identifier): ?Connection
    {
        return $this->connections->firstWhere(
            fn (Connection $connection) => $connection->identifier() === $identifier
        );
    }
}
