<?php

namespace Laravel\Reverb\Contracts;

use Laravel\Reverb\Application;

abstract class Connection
{
    /**
     * The last time the connection was seen.
     */
    protected ?int $lastSeenAt;

    /**
     * Stores the ping state of the connection.
     *
     * @var \Carbon\Carbon
     */
    protected $hasBeenPinged = false;

    protected $pusher;

    public function __construct(
        protected Application $application,
        protected ?string $origin
    ) {
        $this->lastSeenAt = time();
    }

    /**
     * Get the raw socket connection identifier.
     */
    abstract public function identifier(): string;

    /**
     * Get the normalized socket ID.
     */
    abstract public function id(): string;

    /**
     * Send a message to the connection.
     */
    abstract public function send(string $message): void;

    /**
     * Terminate a connection.
     */
    abstract public function terminate(): void;

    /**
     * Get the application the connection belongs to.
     */
    public function app(): Application
    {
        return $this->application;
    }

    /**
     * Get the origin of the connection.
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
    }

    /**
     * Touch the connection last seen at timestamp.
     */
    public function touch(): Connection
    {
        $this->lastSeenAt = time();

        return $this;
    }

    /**
     * Disconnect and unsubscribe from all channels.
     */
    public function disconnect(): void
    {
        $this->terminate();
    }

    /**
     * Determine whether the connection is still active.
     */
    public function isActive(): bool
    {
        return time() < $this->lastSeenAt + $this->app()->pingInterval();
    }

    /**
     * Determine whether the connection is inactive.
     */
    public function isInactive(): bool
    {
        return ! $this->isActive();
    }

    /**
     * Determine whether the connection is stale.
     */
    public function isStale(): bool
    {
        return $this->isInactive() && $this->hasBeenPinged;
    }

    /**
     * Hydrate a serialized connection.
     */
    public static function hydrate(Connection|string $connection): Connection
    {
        return is_object($connection)
            ? $connection
            : unserialize($connection);
    }

    /**
     * Hydrate a serialized connection.
     */
    public static function dehydrate(Connection $connection): Connection|string
    {
        return $connection instanceof SerializableConnection
            ? serialize($connection)
            : $connection;
    }
}
