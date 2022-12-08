<?php

namespace Laravel\Reverb\Contracts;

use Closure;
use Illuminate\Support\Collection;
use Laravel\Reverb\Application;
use Laravel\Reverb\Connection;

interface ConnectionManager
{
    /**
     * The application the channel manager should be scoped to.
     *
     * @param  \Laravel\Reverb\Application  $application
     * @return \Laravel\Reverb\Contracts\ConnectionManager
     */
    public function for(Application $application): ConnectionManager;

    /**
     * Add a new connection to the manager.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return \Laravel\Reverb\Connection $connection
     */
    public function connect(Connection $connection): Connection;

    /**
     * Attempt to find a connection from the manager.
     *
     * @param  string  $identifier
     * @return \Laravel\Reverb\Connection|null $connection
     */
    public function reconnect(string $identifier): ?Connection;

    /**
     * Remove a connection from the manager.
     *
     * @param  string  $identifier
     * @return void
     */
    public function disconnect(string $identifier): void;

    /**
     * Resolve a connection by its identifier.
     *
     * @param  string  $identifier
     * @param  Closure  $connection
     * @return \Laravel\Reverb\Connection
     */
    public function resolve(string $identifier, Closure $newConnection): Connection;

    /**
     * Find a connection by its identifier.
     *
     * @param  string  $identifier
     * @return \Laravel\Reverb\Connection
     */
    public function find(string $identifier): ?Connection;

    /**
     * Get all of the connections from the cache.
     *
     * @return @return \Illuminate\Support\Collection|\Laravel\Reverb\Connection[]
     */
    public function all(): Collection;

    /**
     * Get all of the hydrated connections from the cache.
     *
     * @return @return \Illuminate\Support\Collection|\Laravel\Reverb\Connection[]
     */
    public function hydrated(): Collection;

    /**
     * Synchronize the connections with the manager.
     *
     * @param  \Illuminate\Support\Collection|\Laravel\Reverb\Connection[]|string[]  $connections
     * @return void
     */
    public function sync(Collection $connections): void;

    /**
     * Synchronize a connection with the manager.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return void
     */
    public function syncConnection(Connection $connection): void;

    /**
     * Flush the channel manager repository.
     *
     * @return void
     */
    public function flush(): void;
}
