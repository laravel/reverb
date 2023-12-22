<?php

namespace Laravel\Reverb\Protocols\Pusher\Managers;

use Illuminate\Contracts\Cache\Repository;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;

class CacheChannelConnectionManager implements ChannelConnectionManager
{
    protected string $name;

    /**
     * Create a new cache channel connection manager instance.
     */
    public function __construct(
        protected Repository $repository,
        protected ConnectionManager $connections,
        protected string $prefix = 'reverb'
    ) {
        //
    }

    /**
     * The channel name.
     */
    public function for(string $name): ChannelConnectionManager
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Get the key for the channels.
     */
    protected function key(): string
    {
        return "{$this->prefix}:{$this->name}";
    }

    /**
     * Add a connection.
     */
    public function add(Connection $connection, array $data): void
    {
        $connections = $this->repository->get($this->key(), []);

        $connections[$connection->identifier()] = $data;

        $this->repository->put($this->key(), $connections);
    }

    /**
     * Remove a connection.
     */
    public function remove(Connection $connection): void
    {
        $connections = $this->repository->get($this->key());

        unset($connections[$connection->identifier()]);

        $this->repository->put($this->key(), $connections);
    }

    /**
     * Find a connection in the set.
     */
    public function find(Connection $connection): ?ChannelConnection
    {
        return $this->findById($connection->identifier());
    }

    /**
     * Find a connection in the set by its ID.
     */
    public function findById(string $id): ?ChannelConnection
    {
        $connection = $this->connections->find($id);

        if (! $connection) {
            return null;
        }

        return new ChannelConnection(
            $connection,
            $this->repository->get($this->key())[$id] ?? []
        );
    }

    /**
     * Determine whether any connections remain on the channel.
     */
    public function isEmpty(): bool
    {
        return empty($this->repository->get($this->key(), []));
    }

    /**
     * Get all the connections.
     *
     * @return array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    public function all(): array
    {
        $connections = $this->connections->all();
        $channelConnections = $this->repository->get($this->key(), []);
        $allConnections = array_intersect_key($connections, $channelConnections);

        return array_map(function ($connection) use ($channelConnections) {
            return new ChannelConnection($connection, $channelConnections[$connection->identifier()]);
        }, $allConnections);
    }

    /**
     * Flush the channel connection manager.
     */
    public function flush(): void
    {
        $this->repository->put($this->key(), []);
    }
}
