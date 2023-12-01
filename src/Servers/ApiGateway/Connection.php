<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Concerns\SerializesConnections;
use Laravel\Reverb\Contracts\Connection as BaseConnection;
use Laravel\Reverb\Contracts\ConnectionManager;
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
     */
    public function identifier(): string
    {
        return (string) $this->identifier;
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
        SendToConnection::dispatch($this->identifier, $message);
    }

    /**
     * Terminate a connection.
     */
    public function terminate(): void
    {
        app(ConnectionManager::class)->forget($this);
    }

    /**
     * Ping the connection to ensure it is still active.
     */
    public function ping(): void
    {
        parent::ping();

        $this->save();
    }

    /**
     * Touch the connection last seen at timestamp.
     */
    public function touch(): Connection
    {
        parent::touch();

        $this->save();

        return $this;
    }

    /**
     * Persist the state change to the connection manager.
     */
    public function save(): void
    {
        app(ConnectionManager::class)->update($this);
    }
}
