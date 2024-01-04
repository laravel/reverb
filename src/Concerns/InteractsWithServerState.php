<?php

namespace Laravel\Reverb\Concerns;

use Illuminate\Support\Facades\Cache;

trait InteractsWithServerState
{
    /**
     * The server state cache key.
     *
     * @var string
     */
    protected $key = 'reverb:server';

    /**
     * Deteremine whether the server is running.
     */
    protected function serverIsRunning(): bool
    {
        return Cache::has($this->key);
    }

    /**
     * Get the current state of the running server.
     *
     * @return null|array{HOST: string, PORT: int, DEBUG: bool, RESTART: bool}
     */
    protected function getState(): ?array
    {
        return Cache::get($this->key);
    }

    /**
     * Set the state of the running server.
     */
    protected function setState(string $host, int $port, bool $debug, bool $restart = false): void
    {
        Cache::forever($this->key, [
            'HOST' => $host,
            'PORT' => $port,
            'DEBUG' => $debug ??= false,
            'RESTART' => $restart,
        ]);
    }

    /**
     * Destroy the server state.
     */
    protected function destroyState(): void
    {
        Cache::forget($this->key);
    }
}
