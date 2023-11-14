<?php

namespace Laravel\Reverb\Servers\Reverb;

use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Connection as ReverbConnection;
use Laravel\Reverb\WebSockets\WsConnection;

class Connection extends ReverbConnection
{
    use GeneratesPusherIdentifiers;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    protected $buffer;

    public function __construct(protected WsConnection $connection, Application $application, string $origin = null)
    {
        parent::__construct($application, $origin);
    }

    /**
     * Get the raw socket connection identifier.
     */
    public function identifier(): string
    {
        return (string) $this->connection->resourceId;
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
     * Create a new connection instance.
     */
    public static function make(WsConnection $connection, Application $application, string $origin): Connection
    {
        return new static($connection, $application, $origin);
    }

    /**
     * Send a message to the connection.
     */
    public function send(string $message): void
    {
        $this->connection->send($message);
    }

    /**
     * Terminate a connection.
     */
    public function terminate(): void
    {
        $this->connection->close();
    }
}
