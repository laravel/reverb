<?php

namespace Laravel\Reverb\Channels;

use Exception;
use Illuminate\Support\Arr;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Output;

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

    public function subscribed(Connection $connection): bool
    {
        return $this->connections->find($connection);
    }

    /**
     * Send a message to all connections subscribed to the channel.
     */
    public function broadcast(Application $app, array $payload, Connection $except = null): void
    {
        collect($this->connections())
            ->each(function ($connection) use ($payload, $except) {
                if ($except && $except->identifier() === $connection->connection()->identifier()) {
                    return;
                }

                if (isset($payload['except']) && $payload['except'] === $connection->connection()->identifier()) {
                    return;
                }

                try {
                    $connection->send(
                        json_encode(
                            Arr::except($payload, 'except')
                        )
                    );
                } catch (Exception $e) {
                    Output::error('Broadcasting to '.$connection->id().' resulted in an error');
                    Output::info($e->getMessage());
                }
            });
    }

    /**
     * Get the data associated with the channel.
     */
    public function data(): array
    {
        return [];
    }
}
