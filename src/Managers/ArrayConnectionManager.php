<?php

namespace Laravel\Reverb\Managers;

use Closure;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;

class ArrayConnectionManager implements ConnectionManagerInterface
{
    use InteractsWithApplications;

    /**
     * Connection store.
     *
     * @var array<string, array<string, \Laravel\Reverb\Connection>>
     */
    protected $connections = [];

    /**
     * The appliation instance.
     *
     * @var \Laravel\Reverb\Application
     */
    protected $application;

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

        $this->save($connection);

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
        unset($this->connections[$this->application->id()][$identifier]);
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
        return $this->connections[$this->application->id()][$identifier] ?? null;
    }

    /**
     * Get all of the connections from the cache.
     *
     * @return array<int, \Laravel\Reverb\Connection>
     */
    public function all(): array
    {
        return $this->connections[$this->application->id()] ?? [];
    }

    /**
     * Synchronize a connection with the manager.
     */
    public function save(Connection $connection): void
    {
        $this->connections[$this->application->id()][$connection->id()] = $connection;
    }

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void
    {
        App::make(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                $this->connections[$application->id()] = [];
            });
    }
}
