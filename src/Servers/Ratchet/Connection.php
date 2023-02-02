<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Connection as BaseConnection;
use Laravel\Reverb\Output;
use Ratchet\ConnectionInterface;
use Throwable;

class Connection extends BaseConnection
{
    use GeneratesPusherIdentifiers;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    public function __construct(
        protected ConnectionInterface $connection,
        protected Application $application,
        protected ?string $origin
    ) {
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
     * Get the origin of the connection.
     */
    public function origin(): string
    {
        return $this->origin;
    }

    /**
     * Send a message to the connection.
     */
    public function send(string $message): void
    {
        try {
            $this->connection->send($message);

            Output::info('Message Sent', $this->id());
            Output::message($message);
        } catch (Throwable $e) {
            Output::error('Unable to send message.');
            Output::info($e->getMessage());
        }
    }

    /**
     * Terminate a connection.
     */
    public function terminate(): void
    {
        $this->connection->close();
    }
}
