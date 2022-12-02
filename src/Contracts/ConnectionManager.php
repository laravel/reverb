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
     * Resolve a connection by its identifier.
     *
     * @param  string  $identifier
     * @param  Closure  $connection
     * @return \Laravel\Reverb\Connection
     */
    public function resolve(string $identifier, Closure $newConnection): Connection;

    /**
     * Get all of the connections from the cache.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(): Collection;

    /**
     * Remove a connection from the cache.
     *
     * @param  string  $identifier
     * @return void
     */
    public function disconnect(string $identifier): void;

    /**
     * Synchronise the connections to the cache.
     */
    public function sync(Collection $connections): void;
}
