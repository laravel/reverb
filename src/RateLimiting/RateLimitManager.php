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
     * Check if a connection would exceed rate limits without incrementing the counter.
     */
    public function wouldExceedRateLimit(Connection $connection): bool
    {
        $key = $this->resolveRequestSignature($connection);
        
        return $this->limiter->tooManyAttempts($key, $this->maxAttempts);
    }

    /**
     * Get the number of remaining attempts for a connection.
     */
    public function remainingAttempts(Connection $connection): int
    {
        $key = $this->resolveRequestSignature($connection);
        
        return $this->limiter->remaining($key, $this->maxAttempts);
    }

    /**
     * Get the number of seconds until the rate limit is reset.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return int
     */
    public function availableIn(Connection $connection): int
    {
        $key = $this->resolveRequestSignature($connection);
        
        return $this->limiter->availableIn($key);
    }

    /**
     * Clear the rate limiting for the given connection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public function clear(Connection $connection): void
    {
        $key = $this->resolveRequestSignature($connection);
        
        $this->limiter->clear($key);
    }

    /**
     * Get the maximum number of attempts allowed.
     *
     * @return int
     */
    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Get the decay seconds for the rate limiter.
     *
     * @return int
     */
    public function getDecaySeconds(): int
    {
        return $this->decaySeconds;
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