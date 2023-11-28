<?php

namespace Laravel\Reverb\Channels;

use Exception;
use Illuminate\Support\Arr;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\Connection;

class Channel
{
    /**
     * The channel connections.
     *
     * @var \Laravel\Reverb\Contracts\ChannelConnectionManager
     */
    protected $connections;

    public function __construct(protected string $name)
    {
        $this->connections = app(ChannelConnectionManager::class);
    }

    /**
     * Get the channel name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get all connections for the channel.
     */
    public function connections(): array
    {
        return $this->connections->all();
    }

    /**
     * Find a connection.
     */
    public function find(Connection $connection): ?Connection
    {
        return $this->connections->find($connection);
    }

    /**
     * Find a connection by its ID.
     */
    public function findById(string $id): ?Connection
    {
        return $this->connections->findById($id);
    }

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, string $auth = null, string $data = null): void
    {
        $this->connections->add($connection, $data ? json_decode($data, true) : []);
    }

    /**
     * Unsubscribe from the given channel.
     */
    public function unsubscribe(Connection $connection): void
    {
        $this->connections->remove($connection);
    }

    /**
     * Determine if the connection is subscribed to the channel.
     */
    public function subscribed(Connection $connection): bool
    {
        return $this->connections->find($connection) !== null;
    }

    /**
     * Send a message to all connections subscribed to the channel.
     */
    public function broadcast(array $payload, Connection $except = null): void
    {
        $message = json_encode(
            Arr::except($payload, 'except')
        );

        $chunks = array_chunk($this->connections(), 100);

        foreach ($chunks as $connections) {
            foreach ($connections as $connection) {
                if ($except && $except->id() === $connection->connection()->id()) {
                    continue;
                }

                if (isset($payload['except']) && $payload['except'] === $connection->connection()->id()) {
                    continue;
                }

                try {
                    $connection->send($message);
                } catch (Exception $e) {
                    //
                }
            }
        }
    }

    /**
     * Broadcast a message triggered from an internal source.
     */
    public function broadcastInternally(array $payload, Connection $except = null): void
    {
        $this->broadcast($payload, $except);
    }

    /**
     * Get the data associated with the channel.
     */
    public function data(): array
    {
        return [];
    }
}
