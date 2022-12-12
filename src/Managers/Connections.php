<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Support\Collection;
use Laravel\Reverb\Connection;

class Connections extends Collection
{
    /**
     * Find a connection in the collection.
     *
     * @param  string $identifier
     * @return \Laravel\Reverb\Connection|null
     */
    public function find($identifier)
    {
        if(! $connection = parent::get($identifier)) {
            return null;
        }

        return Connection::hydrate($connection);
    }

    /**
     * Execute a callback over each hydrated connection.
     *
     * @param  callable(TValue, TKey): mixed  $callback
     * @return $this
     */
    public function each(callable $callback)
    {
        foreach ($this as $key => $item) {
            if ($callback(Connection::hydrate($item), $key) === false) {
                break;
            }
        }

        return $this;
    }
}