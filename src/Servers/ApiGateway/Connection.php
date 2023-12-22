<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Laravel\Reverb\Contracts\WebSocketConnection;
use Laravel\Reverb\Servers\ApiGateway\Jobs\SendToConnection;

class Connection implements WebSocketConnection
{
    /**
     * Create a new connection instance.
     */
    public function __construct(protected string $identifier)
    {
        //
    }

    /**
     * Get the raw socket connection identifier.
     */
    public function id(): int|string
    {
        return $this->identifier;
    }

    /**
     * Send a message to the connection.
     */
    public function send(mixed $message): void
    {
        SendToConnection::dispatch($this->identifier, $message);
    }

    /**
     * Terminate a connection.
     */
    public function close(mixed $message = null): void
    {
        //
    }
}
