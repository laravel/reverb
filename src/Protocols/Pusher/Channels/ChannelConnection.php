<?php

namespace Laravel\Reverb\Protocols\Pusher\Channels;

use Illuminate\Support\Arr;
use Laravel\Reverb\Contracts\Connection;

class ChannelConnection
{
    /**
     * The time at which the connection subscribed to the channel.
     */
    protected float $subscribedAt;

    /**
     * Create a new channel connection instance.
     */
    public function __construct(protected Connection $connection, protected array $data = [])
    {
        $this->subscribedAt = microtime(true);
    }

    /**
     * Get the underlying connection.
     */
    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * Get the connection data.
     */
    public function data(?string $key = null): mixed
    {
        return $key ? Arr::get($this->data, $key) : $this->data;
    }

    /**
     * Get the time at which the connection subscribed to the channel.
     */
    public function subscribedAt(): float
    {
        return $this->subscribedAt;
    }

    /**
     * Send a message to the connection.
     */
    public function send(string $message): void
    {
        $this->connection->send($message);
    }

    /**
     * Proxy the given method to the underlying connection.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->connection->{$method}(...$parameters);
    }
}
