<?php

namespace Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Reverb\Channels\Channel;
use Reverb\Channels\ChannelBroker;
use Reverb\Concerns\EnsuresIntegrity;
use Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Reverb\Contracts\Connection;
use Reverb\Contracts\ConnectionManager;

class ChannelManager implements ChannelManagerInterface
{
    use EnsuresIntegrity;

    public function __construct(
        protected Repository $repository,
        protected ConnectionManager $connections,
        protected $prefix = 'reverb'
    ) {
    }

    /**
     * Subscribe to a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Contracts\Connection  $connection
     * @param  array  $data
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection, $data = []): void
    {
        $connections = $this->connections($channel)
            ->put($connection->identifier(), $data);

        $this->syncConnections($channel, $connections);
    }

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Contracts\Connection  $connection
     * @return void
     */
    public function unsubscribe(Channel $channel, Connection $connection): void
    {
        $connections = $this->connections($channel)
            ->reject(fn ($data, $identifier) => $identifier === $connection->identifier());

        $this->syncConnections($channel, $connections);
    }

    /**
     * Get all the channels.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(): Collection
    {
        return $this->channels()->map(function ($connections, $name) {
            return ChannelBroker::create($name);
        });
    }

    /**
     * Unsubscribe from all channels.
     *
     * @return void
     */
    public function unsubscribeFromAll(Connection $connection): void
    {
        $this->channels()->each(function ($connections, $name) use ($connection) {
            ChannelBroker::create($name)->unsubscribe($connection);
        });
    }

    /**
     * Get all connections subscribed to a channel.
     *
     * @return \Illuminate\Support\Collection
     */
    public function connections(Channel $channel): Collection
    {
        return $this->channel($channel);
    }

    /**
     * Sync the connections for a channel.
     *
     * @param  Channel  $channel
     * @param  Collection  $connections
     * @return void
     */
    protected function syncConnections(Channel $channel, Collection $connections): void
    {
        $channels = $this->channels();

        $this->mutex(function () use ($channel, $connections, $channels) {
            $channels[$channel->name()] = $connections;

            $this->repository->forever($this->key(), $channels);
        });
    }

    /**
     * Get the key for the channels.
     *
     * @return string
     */
    protected function key(): string
    {
        return "{$this->prefix}:channels";
    }

    /**
     * Get the given channel from the cache.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @return \Illuminate\SUpport\Collection
     */
    protected function channel(Channel $channel): Collection
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels from the cache.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @return \Illuminate\SUpport\Collection
     */
    protected function channels(Channel $channel = null): Collection
    {
        return $this->mutex(function () use ($channel) {
            $channels = $this->repository->get($this->key(), []);

            if ($channel) {
                return collect($channels[$channel->name()] ?? []);
            }

            return collect($channels ?: []);
        });
    }

    /**
     * Get that data stored for a connection.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @return array
     */
    public function data(Channel $channel, Connection $connection): array
    {
        return (array) $this->connections($channel)
            ->get($connection->identifier(), []);
    }
}
