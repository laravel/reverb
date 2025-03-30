<?php

namespace Laravel\Reverb\RateLimiting;

use Illuminate\Cache\RateLimiter;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException;

class RateLimitManager
{
    /**
     * Create a new rate limit manager instance.
     */
    public function __construct(
        protected RateLimiter $limiter, 
        protected int $maxAttempts, 
        protected int $decaySeconds,
        protected bool $terminateOnLimit = true
    ) {
        //
    }

    /**
     * Handle the incoming message and apply rate limiting.
     * 
     * @throws \Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException
     */
    public function handle(Connection $connection): void
    {
        $key = $this->resolveRequestSignature($connection);

        if ($this->limiter->tooManyAttempts($key, $this->maxAttempts)) {
            if ($this->terminateOnLimit) {
                $connection->terminate();
            }

            throw new RateLimitExceededException();
        }

        $this->limiter->hit($key, $this->decaySeconds);
    }

    /**
     * Resolve the request signature for the given connection.
     */
    protected function resolveRequestSignature(Connection $connection): string
    {
        return sha1(implode('|', [
            $connection->id(),
            $connection->app()->id(),
        ]));
    }
} 