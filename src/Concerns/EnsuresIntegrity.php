<?php

namespace Laravel\Reverb\Concerns;

use Closure;

trait EnsuresIntegrity
{
    /**
     * Ensure this is the only running instance.
     */
    protected function mutex(Closure $callback, $timeout = 10): mixed
    {
        if (! property_exists($this, 'repository')) {
            return $callback();
        }

        if (! method_exists($this->repository->getStore(), 'lock')) {
            return $callback();
        }

        return $this->repository->lock($this->mutexKey(), $timeout)
            ->block($timeout, function () use ($callback) {
                return $callback();
            });
    }

    /**
     * Get the mutex category key.
     */
    protected function key(): string
    {
        return 'mutex';
    }

    /**
     * Get the mutex key.
     */
    protected function mutexKey(): string
    {
        return "{$this->key()}:mutex";
    }
}
