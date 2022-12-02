<?php

namespace Laravel\Reverb\Managers;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Contracts\SerializableConnection;

class ConnectionManager implements ConnectionManagerInterface
{
    use EnsuresIntegrity, InteractsWithApplications;

    /**
     * The appliation instance.
     *
     * @var \Laravel\Reverb\Application
     */
    protected $application;

    public function __construct(
        protected Repository $repository,
        protected $prefix = 'reverb'
    ) {
    }

    /**
     * Resolve a connection by its identifier.
     *
     * @param  string  $identifier
     * @param  Closure  $connection
     * @return \Laravel\Reverb\Connection
     */
    public function resolve(string $identifier, Closure $newConnection): Connection
    {
        $connections = $this->all();

        if (! $connection = $connections->get($identifier)) {
            $connection = $newConnection();
        }

        $connection = $this->hydrate($connection);
        $connection->touch();

        $this->sync(
            $connections->put($identifier, $this->dehydrate($connection))
        );

        return $connection;
    }

    /**
     * Get all of the connections from the cache.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(): Collection
    {
        return $this->mutex(function () {
            return $this->repository->get($this->key()) ?? collect();
        });
    }

    /**
     * Get all of the hydrated connections from the cache.
     *
     * @return \Illuminate\Support\Collection
     */
    public function hydrated(): Collection
    {
        return $this->all()->map(fn ($connection) => $this->hydrate($connection));
    }

    /**
     * Remove a connection from the cache.
     *
     * @param  string  $identifier
     * @return void
     */
    public function disconnect(string $identifier): void
    {
        $connections = $this->all();

        $this->sync(
            $connections->reject(fn ($connection, $id) => (string) $id === $identifier)
        );
    }

    /**
     * Synchronise the connections to the cache.
     */
    public function sync(Collection $connections): void
    {
        $this->mutex(function () use ($connections) {
            $this->repository->forever($this->key(), $connections);
        });
    }

    /**
     * Get the key for the channels.
     *
     * @return string
     */
    protected function key(): string
    {
        $key = $this->prefix;

        if ($this->application) {
            $key .= ":{$this->application->id()}";
        }

        return $key .= ':connections';
    }

    /**
     * Hydrate a serialized connection.
     *
     * @param  \Laravel\Reverb\Connection|string  $connection
     * @return \Laravel\Reverb\Connection
     */
    protected function hydrate($connection): Connection
    {
        return is_object($connection)
            ? $connection
            : unserialize($connection);
    }

    /**
     * Hydrate a serialized connection.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return \Laravel\Reverb\Connection|string
     */
    protected function dehydrate($connection): Connection|string
    {
        return $connection instanceof SerializableConnection
            ? serialize($connection)
            : $connection;
    }
}
