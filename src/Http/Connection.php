<?php

namespace Laravel\Reverb\Http;

use BadMethodCallException;
use React\Socket\ConnectionInterface;

class Connection
{
    /**
     * Connection ID.
     */
    protected int $id;

    /**
     * Connection status.
     */
    protected bool $connected = false;

    /**
     * Connection buffer.
     */
    protected string $buffer = '';

    public function __construct(protected ConnectionInterface $connection)
    {
        $this->id = (int) $connection->stream;
    }

    /**
     * Return the connection ID.
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * Mark the connection as connected.
     */
    public function connect(): void
    {
        $this->connected = true;
    }

    /**
     * Determine whether the connection is connected.
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Get the HTTP message buffer.
     */
    public function buffer(): string
    {
        return $this->buffer;
    }

    /**
     * Determine whether the connection has an HTTP message buffer set.
     */
    public function hasBuffer()
    {
        return $this->buffer !== '';
    }

    /**
     * Return the HTTP message buffer length.
     */
    public function bufferLength()
    {
        return strlen($this->buffer);
    }

    /**
     * Append to the HTTP message buffer.
     */
    public function appendToBuffer($message)
    {
        $this->buffer .= $message;
    }

    /**
     * Clear the HTTP message buffer.
     */
    public function clearBuffer()
    {
        $this->buffer = '';
    }

    /**
     * Send a message to the connection.
     */
    public function send($data)
    {
        $this->connection->write($data);

        return $this;
    }

    /**
     * Close the connection.
     */
    public function close()
    {
        $this->connection->close();

        return $this;
    }

    /**
     * Dynamically proxy method calls to the underlying connection.
     */
    public function __call($method, $parameters)
    {
        if (! method_exists($this->connection, $method)) {
            throw new BadMethodCallException("Method [{$method}] does not exist on [".get_class($this->connection).'].');
        }

        return $this->connection->{$method}(...$parameters);
    }
}
