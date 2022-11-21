<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\SerializableConnection;

class ChannelManager implements ChannelManagerInterface
{
    use EnsuresIntegrity;

    public function __construct(
        protected Repository $repository,
        protected $prefix = 'reverb'
    ) {
    }

    /**
     * Subscribe to a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  array  $data
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection, $data = []): void
    {
        $connections = $this->connections($connection->application(), $channel)
            ->put($connection->identifier(), [
                'connection' => $this->shouldBeSerialized($connection) ? serialize($connection) : $connection,
                'data' => $data,
            ]);

        $this->syncConnections($connection->application(), $channel, $connections);
    }

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public function unsubscribe(Channel $channel, Connection $connection): void
    {
        $connections = $this->connections($connection->application(), $channel)
            ->reject(fn ($data, $identifier) => (string) $identifier === $connection->identifier());

        $this->syncConnections($connection->application(), $channel, $connections);
    }

    /**
     * Get all the channels.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(Application $app): Collection
    {
        return $this->channels($app)->map(function ($connections, $name) {
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
        $this->channels($connection->application())->each(function ($connections, $name) use ($connection) {
            ChannelBroker::create($name)->unsubscribe($connection);
        });
    }

    /**
     * Get all connections subscribed to a channel.
     *
     * @return \Illuminate\Support\Collection
     */
    public function connections(Application $app, Channel $channel): Collection
    {
        return $this->channel($app, $channel);
    }

    /**
     * Sync the connections for a channel.
     *
     * @param  Channel  $channel
     * @param  Collection  $connections
     * @return void
     */
    protected function syncConnections(Application $app, Channel $channel, Collection $connections): void
    {
        $channels = $this->channels($app);

        $this->mutex(function () use ($app, $channel, $connections, $channels) {
            $channels[$channel->name()] = $connections;

            $this->repository->forever($this->key($app->id()), $channels);
        });
    }

    /**
     * Get the key for the channels.
     *
     * @return string
     */
    protected function key($appId): string
    {
        return "{$this->prefix}:channels:{$appId}";
    }

    /**
     * Get the given channel from the cache.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\SUpport\Collection
     */
    protected function channel(Application $app, Channel $channel): Collection
    {
        return $this->channels($app, $channel);
    }

    /**
     * Get the channels from the cache.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\SUpport\Collection
     */
    protected function channels(Application $app, Channel $channel = null): Collection
    {
        return $this->mutex(function () use ($app, $channel) {
            $channels = $this->repository->get($this->key($app->id()), []);

            if ($channel) {
                return collect($channels[$channel->name()] ?? []);
            }

            return collect($channels ?: []);
        });
    }

    /**
     * Get the data stored for a connection.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return array
     */
    public function data(Channel $channel, Connection $connection): array
    {
        if (! $connection = $this->connections($connection->application(), $channel)->get($connection->identifier())) {
            return [];
        }

        return (array) $connection['data'];
    }

    /**
     * Determine whether the connection should be serialized.
     *
     * @param  Connection  $connection
     * @return bool
     */
    protected function shouldBeSerialized(Connection $connection): bool
    {
        return $connection instanceof SerializableConnection;
    }
}
