<?php

namespace Laravel\Reverb;

use Carbon\Carbon;

abstract class Connection
{
    protected Carbon $lastSeenAt;

    public function __construct(protected Application $application)
    {
        $this->lastSeenAt = now();
    }

    /**
     * Get the raw socket connection identifier.
     *
     * @return string
     */
    abstract public function identifier(): string;

    /**
     * Get the normalized socket ID.
     *
     * @return string
     */
    abstract public function id(): string;

    /**
     * Send a message to the connection.
     *
     * @param  string  $message
     * @return void
     */
    abstract public function send(string $message): void;

    /**
     * Get the application the connection belongs to.
     *
     * @return \Laravel\Reverb\Application
     */
    abstract public function app(): Application;

    /**
     * Get the last time the connection was seen.
     *
     * @return \Carbon\Carbon
     */
    public function lastSeenAt(): Carbon
    {
        return $this->lastSeenAt;
    }

    /**
     * Determine whether the connection is still active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->lastSeenAt &&
            $this->lastSeenAt->isBefore(
                now()->subMinutes(
                    $this->app()->pingInterval()
                )
            );
    }

    /**
     * Determine whether the connection is stale.
     *
     * @return bool
     */
    public function isStale(): bool
    {
        return ! $this->isActive();
    }
}
