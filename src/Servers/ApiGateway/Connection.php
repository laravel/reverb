<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Concerns\SerializesConnections;
use Laravel\Reverb\Connection as BaseConnection;
use Laravel\Reverb\Contracts\SerializableConnection;
use Laravel\Reverb\Servers\ApiGateway\Jobs\SendToConnection;

class Connection extends BaseConnection implements SerializableConnection
{
    use GeneratesPusherIdentifiers, SerializesConnections;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    public function __construct(
        protected string $identifier,
        protected Application $application,
        protected ?string $origin
    ) {
        parent::__construct($application, $origin);
    }

    /**
     * Get the raw socket connection identifier.
     *
     * @return string
     */
    public function identifier(): string
    {
        return (string) $this->identifier;
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
        SendToConnection::dispatch($this->identifier, $message);
    }

    /**
     * Terminate a connection.
     *
     * @return void
     */
    public function disconnect(): void
    {
        //
    }
}
