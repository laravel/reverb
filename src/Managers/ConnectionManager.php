<?php

namespace Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Reverb\Connection;
use Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Traversable;

class ConnectionManager implements ConnectionManagerInterface
{
    protected Collection $connections;

    public function __construct(protected Repository $repository, protected $prefix = 'reverb')
    {
        $this->connections = collect($this->repository->get($this->key(), []));
    }

    /**
     * Determine the cache key.
     *
     * @return string
     */
    protected function key()
    {
        return "{$this->prefix}:connections";
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

        $this->repository->forever($this->key(), $this->connections);

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

        $this->repository->forever($this->key(), $this->connections);
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
