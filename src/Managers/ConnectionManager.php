<?php

namespace Laravel\Reverb\Managers;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;

class ConnectionManager implements ConnectionManagerInterface
{
    use EnsuresIntegrity, InteractsWithApplications;

    protected $connections;

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
     * Add a new connection to the manager.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return \Laravel\Reverb\Connection $connection
     */
    public function connect(Connection $connection): Connection
    {
        $connection->touch();

        $this->syncConnection($connection);

        return $connection;
    }

    /**
     * Attempt to find a connection from the manager.
     *
     * @param  string  $identifier
     * @return \Laravel\Reverb\Connection|null $connection
     */
    public function reconnect(string $identifier): ?Connection
    {
        if ($connection = $this->find($identifier)) {
            return $connection->touch();
        }

        return null;
    }

    /**
     * Remove a connection from the manager.
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
     * Resolve a connection by its identifier.
     *
     * @param  string  $identifier
     * @param  Closure  $connection
     * @return \Laravel\Reverb\Connection
     */
    public function resolve(string $identifier, Closure $newConnection): Connection
    {
        if (! $connection = $this->find($identifier)) {
            $connection = $newConnection();
        }

        return $this->connect($connection);
    }

    /**
     * Find a connection by its identifier.
     *
     * @param  string  $identifier
     * @return \Laravel\Reverb\Connection
     */
    public function find(string $identifier): ?Connection
    {
        if ($connection = $this->all()->find($identifier)) {
            return $connection;
        }

        return null;
    }

    /**
     * Get all of the connections from the cache.
     *
     * @return \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]
     */
    public function all(): Connections
    {
        return $this->mutex(function () {
            return $this->repository->get($this->key()) ?? new Connections;
        });
    }

    /**
     * Synchronize the connections with the manager.
     *
     * @param  \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]  $connections
     * @return void
     */
    public function sync(Connections $connections): void
    {
        $this->mutex(function () use ($connections) {
            $this->repository->forever($this->key(), $connections);
        });
    }

    /**
     * Synchronize a connection with the manager.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return void
     */
    public function syncConnection(Connection $connection): void
    {
        $this->sync(
            $this->all()->put(
                $connection->identifier(),
                Connection::dehydrate($connection)
            )
        );
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
     * Flush the channel manager repository.
     *
     * @return void
     */
    public function flush(): void
    {
        Application::all()->each(function ($application) {
            $this->for($application);
            $this->repository->forget($this->key());
        });
    }
}
