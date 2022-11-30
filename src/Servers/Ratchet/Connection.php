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
        protected Application $application
    ) {
        parent::__construct($application);
    }

    /**
     * Get the raw socket connection identifier.
     *
     * @return string
     */
    public function identifier(): string
    {
        return (string) $this->connection->resourceId;
    }

    /**
     * Get the normalized socket ID.
     *
     * @return string
     */
    public function id(): string
    {
        if (! isset($this->connection->id)) {
            $this->connection->id = $this->generateId();
        }

        return $this->connection->id;
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
            Output::error('Unable to send message.');
            Output::info($e->getMessage());
        }
    }

    /**
     * Get the application the connection belongs to.
     *
     * @return \Laravel\Reverb\Application
     */
    public function app(): Application
    {
        return $this->application;
    }
}
