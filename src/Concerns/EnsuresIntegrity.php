<?php

namespace Laravel\Reverb\Concerns;

use Closure;

trait EnsuresIntegrity
{
    /**
     * Ensure this is the only running instance.
     *
     * @param  Closure  $callback
     * @param  int  $timeout
     * @return mixed
     */
    protected function mutex(Closure $callback, $timeout = 10)
    {
        if (! property_exists($this, 'repository')) {
            return $callback();
        }

        if (! method_exists($this->repository, 'lock')) {
            return $callback();
        }

        return $this->repository->lock($this->key(), $timeout)
            ->block($timeout, function () use ($callback) {
                return $callback();
            });
    }

    /**
     * Get the mutex category key.
     *
     * @return string
     */
    protected function key(): string
    {
        return 'mutex';
    }

    /**
     * Get the mutex key.
     *
     * @return string
     */
    protected function mutexKey(): string
    {
        return "{$this->key()}:mutex";
    }
}
