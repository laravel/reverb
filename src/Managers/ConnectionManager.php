<?php

namespace Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Reverb\Concerns\EnsuresIntegrity;
use Reverb\Contracts\Connection;
use Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Traversable;

class ConnectionManager implements ConnectionManagerInterface
{
    use EnsuresIntegrity;

    protected Collection $connections;

    public function __construct(
        protected Repository $repository,
        protected $prefix = 'reverb'
    ) {
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
     * @param  \Reverb\Contracts\Connection  $connection
     * @return void
     */
    public function connect(Connection $connection): Connection
    {
        $this->add($connection);

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
        $connections = $this->connections()->forget($connection->identifier());

        $this->mutex(function () use ($connections) {
            $this->repository->forever(
                $this->key(),
                $connections
            );
        });
    }

    /**
     * Get all connections.
     *
     * @return \Traversable
     */
    public function all(): Traversable
    {
        return $this->connections();
    }

    /**
     * Get a connection by its identifier.
     *
     * @param  string  $identifier
     * @return \Reverb\Contracts\Connection  $connection
     */
    public function get(string $identifier): ?Connection
    {
        $connection = $this->connections()->firstWhere(
            fn (Connection $connection) => $connection->identifier() === $identifier
        );

        if (! $connection) {
            return null;
        }

        return is_object($connection) ? $connection : unserialize($connection);
    }

    /**
     * Add a new connection to the collection.
     *
     * @param  Connection  $connection
     * @return void
     */
    protected function add(Connection $connection): void
    {
        if ($this->shouldBeSerialized($connection)) {
            $connection = ['serialized' => serialize($connection)];
        }

        $connections = $this->connections()->put($connection->identifier(), $connection);

        $this->mutex(function () use ($connections) {
            $this->repository->forever(
                $this->key(),
                $connections
            );
        });
    }

    /**
     * Determine whether the connection should be serialized.
     *
     * @param  Connection  $connection
     * @return bool
     */
    protected function shouldBeSerialized(Connection $connection): bool
    {
        return in_array(SerializesConnections::class, class_uses($connection));
    }

    protected function connections(): Collection
    {
        return $this->mutex(function () {
            collect($this->repository->get($this->key(), []));
        });
    }
}
