<?php

namespace Laravel\Reverb\Contracts;

use Closure;
use Laravel\Reverb\Application;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Managers\Connections;

interface ConnectionManager
{
    /**
     * Get the application instance.
     */
    public function app(): ?Application;

    /**
     * The application the channel manager should be scoped to.
     */
    public function for(Application $application): ConnectionManager;

    /**
     * Add a new connection to the manager.
     */
    public function connect(Connection $connection): Connection;

    /**
     * Attempt to find a connection from the manager.
     */
    public function reconnect(string $identifier): ?Connection;

    /**
     * Remove a connection from the manager.
     */
    public function disconnect(string $identifier): void;

    /**
     * Resolve a connection by its identifier.
     */
    public function resolve(string $identifier, Closure $newConnection): Connection;

    /**
     * Find a connection by its identifier.
     */
    public function find(string $identifier): ?Connection;

    /**
     * Get all of the connections from the cache.
     *
     * @return \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]
     */
    public function all(): Connections;

    /**
     * Synchronize the connections with the manager.
     *
     * @param  \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]  $connections
     */
    public function sync(Connections $connections): void;

    /**
     * Synchronize a connection with the manager.
     */
    public function syncConnection(Connection $connection): void;

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void;
}
