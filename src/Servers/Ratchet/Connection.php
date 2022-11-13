<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Ratchet\ConnectionInterface as RatchetConnectionInterface;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Contracts\Connection as ConnectionInterface;
use Throwable;

class Connection implements ConnectionInterface
{
    use GeneratesPusherIdentifiers;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    public function __construct(protected RatchetConnectionInterface $connection)
    {
    }

    /**
     * Get the raw socket connection identifier.
     *
     * @return string
     */
    public function identifier(): string
    {
        return $this->connection->resourceId;
    }

    /**
     * Get the normalized socket ID.
     *
     * @return string
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
     *
     * @param  string  $message
     * @return void
     */
    public function send(string $message): void
    {
        try {
            $this->connection->send($message);
        } catch (Throwable $e) {
            echo 'Unable to send message to connection: '.$e->getMessage();
        }
    }
}
