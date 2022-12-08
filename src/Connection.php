<?php

namespace Laravel\Reverb;

use Carbon\Carbon;

abstract class Connection
{
    /**
     * The last time the connection was seen.
     *
     * @var string
     */
    protected string $lastSeenAt;

    /**
     * Stores the ping state of the connection.
     *
     * @var \Carbon\Carbon
     */
    protected $hasBeenPinged = false;

    public function __construct(protected Application $application, protected ?string $origin)
    {
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
     * Terminate a connection.
     *
     * @return void
     */
    abstract public function disconnect(): void;

    /**
     * Get the application the connection belongs to.
     *
     * @return \Laravel\Reverb\Application
     */
    public function app(): Application
    {
        return $this->application;
    }

    /**
     * Get the origin of the connection.
     *
     * @return string|null
     */
    public function origin(): ?string
    {
        return $this->origin;
    }

    /**
     * Ping the connection to ensure it is still active.
     */
    public function ping(): void
    {
        $this->hasBeenPinged = true;

        PusherEvent::ping($this);

        Output::info('Connection Pinged', $this->id());
    }

    /**
     * Touch the connection last seen at timestamp.
     *
     * @return \Laravel\Reverb\Connection
     */
    public function touch(): Connection
    {
        $this->lastSeenAt = (string) now();

        return $this;
    }

    /**
     * Get the last time the connection was seen.
     *
     * @return \Carbon\Carbon
     */
    public function lastSeenAt(): Carbon
    {
        return Carbon::parse($this->lastSeenAt);
    }

    /**
     * Determine whether the connection is still active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->lastSeenAt() &&
            $this->lastSeenAt()->isAfter(
                now()->subMinutes(
                    $this->app()->pingInterval()
                )
            );
    }

    /**
     * Determine whether the connection is inactive.
     *
     * @return bool
     */
    public function isInactive(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Determine whether the connection is stale.
     *
     * @return bool
     */
    public function isStale(): bool
    {
        return $this->isInactive() && $this->hasBeenPinged;
    }
}
