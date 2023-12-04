<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\ConnectionManager;

class CacheConnectionManager implements ConnectionManager
{
    public function __construct(
        protected Repository $repository,
        protected $prefix = 'reverb'
    ) {
        //
    }

    /**
     * Get the key for the channels.
     */
    protected function key(): string
    {
        return "{$this->prefix}:connections";
    }

    /**
     * Add a new connection.
     */
    public function connect(Connection $connection): void
    {
        $connections = $this->get();

        $connections[$connection->identifier()] = serialize($connection);

        $this->persist($connections);
    }

    /**
     * Find a connection.
     */
    public function find(string $id): ?Connection
    {
        $connections = $this->get();

        if (! isset($connections[$id])) {
            return null;
        }

        return unserialize($connections[$id]);
    }

    /**
     * Get all the connections.
     */
    public function all(): array
    {
        return array_map('unserialize', $this->get());
    }

    /**
     * Update the state of a connection.
     */
    public function update(Connection $connection): void
    {
        $this->connect($connection);
    }

    /**
     * Forget a connection.
     */
    public function forget(Connection $connection): void
    {
        $connections = $this->get();

        unset($connections[$connection->identifier()]);

        $this->persist($connections);
    }

    /**
     * Flush all connections.
     */
    public function flush(): void
    {
        $this->repository->forget($this->key());
    }

    /**
     * Get all the connections from the store.
     */
    protected function get(): array
    {
        return $this->repository->get($this->key(), []);
    }

    /**
     * Persist the connections to the store.
     */
    protected function persist(array $connections): void
    {
        $this->repository->put($this->key(), $connections);
    }
}
