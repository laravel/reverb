<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\Connection;

class ConnectionRegistry
{
    /**
     * The open connections, keyed by application ID and socket ID.
     *
     * @var array<string, array<string, Connection>>
     */
    protected array $connections = [];

    /**
     * Add an open connection.
     */
    public function add(Connection $connection): void
    {
        $this->connections[$connection->app()->id()][$connection->id()] = $connection;
    }

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void
    {
        unset($this->connections[$connection->app()->id()][$connection->id()]);
    }

    /**
     * Get the open connections for the given application.
     *
     * @return array<string, Connection>
     */
    public function for(Application $application): array
    {
        return $this->connections[$application->id()] ?? [];
    }

    /**
     * Count the open connections for the given application.
     */
    public function count(Application $application): int
    {
        return count($this->for($application));
    }
}
