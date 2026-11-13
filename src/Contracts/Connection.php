<?php

namespace Laravel\Reverb\Contracts;

use Laravel\Reverb\Application;
use Ratchet\RFC6455\Messaging\Frame;

abstract class Connection
{
    /**
     * The last time the connection was seen.
     */
    protected ?int $lastSeenAt;

    /**
     * Stores the ping state of the connection.
     */
    protected $hasBeenPinged = false;

    /**
     * Indicates if the connection uses control frames.
     */
    protected $usesControlFrames = false;

    /**
     * The names of the channels the connection is subscribed to.
     *
     * @var array<string, true>
     */
    protected array $channels = [];

    /**
     * Create a new connection instance.
     */
    public function __construct(protected WebSocketConnection $connection, protected Application $application, protected ?string $origin)
    {
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
     * Send a control frame to the connection.
     */
    abstract public function control(string $type = Frame::OP_PING): void;

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
     * Mark the connection as pinged.
     */
    public function ping(): void
    {
        $this->hasBeenPinged = true;
    }

    /**
     * Mark the connection as ponged.
     */
    public function pong(): void
    {
        $this->hasBeenPinged = false;
    }

    /**
     * Get the last time the connection was seen.
     */
    public function lastSeenAt(): ?int
    {
        return $this->lastSeenAt;
    }

    /**
     * Set the connection last seen at timestamp.
     */
    public function setLastSeenAt(int $time): Connection
    {
        $this->lastSeenAt = $time;

        return $this;
    }

    /**
     * Touch the connection last seen at timestamp.
     */
    public function touch(): Connection
    {
        $this->setLastSeenAt(time());
        $this->pong();

        return $this;
    }

    /**
     * Record that the connection subscribed to the given channel.
     */
    public function addChannel(string $channel): void
    {
        $this->channels[$channel] = true;
    }

    /**
     * Record that the connection unsubscribed from the given channel.
     */
    public function removeChannel(string $channel): void
    {
        unset($this->channels[$channel]);
    }

    /**
     * Determine whether the connection may subscribe to the given channel without exceeding its channel limit.
     */
    public function canSubscribeTo(string $channel): bool
    {
        return isset($this->channels[$channel])
            || count($this->channels) < $this->app()->maxChannelsPerConnection();
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
     * Determine whether the connection uses control frames.
     */
    public function usesControlFrames(): bool
    {
        return $this->usesControlFrames;
    }

    /**
     * Mark the connection as using control frames to track activity.
     */
    public function setUsesControlFrames(bool $usesControlFrames = true): Connection
    {
        $this->usesControlFrames = $usesControlFrames;

        return $this;
    }
}
