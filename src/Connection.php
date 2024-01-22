<?php

namespace Laravel\Reverb;

use Laravel\Reverb\Concerns\GeneratesIdentifiers;
use Laravel\Reverb\Contracts\Connection as ConnectionContract;
use Laravel\Reverb\Events\MessageSent;

class Connection extends ConnectionContract
{
    use GeneratesIdentifiers;

    /**
     * The normalized socket ID.
     */
    protected ?string $id = null;

    /**
     * Stores the ping state of the connection.
     */
    protected $hasBeenPinged = false;

    /**
     * Get the raw socket connection identifier.
     */
    public function identifier(): string
    {
        return (string) $this->connection->id();
    }

    /**
     * Get the normalized socket ID.
     */
    public function id(): string
    {
        if (! $this->id) {
            $this->id = $this->generateId();
        }

        return $this->id;
    }

    /**
     * Send a message to the connection.
     */
    public function send(string $message): void
    {
        $this->connection->send($message);

        MessageSent::dispatch($this, $message);
    }

    /**
     * Terminate a connection.
     */
    public function terminate(): void
    {
        $this->connection->close();
    }
}
