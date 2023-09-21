<?php

namespace Laravel\Reverb\Managers;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\ApplicationProvider;
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
     * Get the application instance.
     */
    public function app(): ?Application
    {
        return $this->application;
    }

    /**
     * Add a new connection to the manager.
     */
    public function connect(Connection $connection): Connection
    {
        $connection->touch();

        $this->syncConnection($connection);

        return $connection;
    }

    /**
     * Attempt to find a connection from the manager.
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
            return $this->repository->get($this->key()) ?: new Connections;
        });
    }

    /**
     * Synchronize the connections with the manager.
     *
     * @param  \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]  $connections
     */
    public function sync(Connections $connections): void
    {
        $this->mutex(function () use ($connections) {
            $this->repository->forever($this->key(), $connections);
        });
    }

    /**
     * Synchronize a connection with the manager.
     */
    public function syncConnection(Connection $connection): void
    {
        $connections = $this->all()->put(
            $connection->identifier(),
            Connection::dehydrate($connection)
        );

        $this->sync($connections);
    }

    /**
     * Get the key for the channels.
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
     */
    public function flush(): void
    {
        App::make(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                $this->for($application);
                $this->repository->forget($this->key());
            });
    }
}
