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

    protected $application;

    public function __construct(
        protected Repository $repository,
        protected $prefix = 'reverb'
    ) {
    }

    /**
     * Set an the application to scope the channel manager to.
     *
     * @param  \Laravel\Reverb\Application  $application
     * @return self
     */
    public function for(Application $application): self
    {
        $this->application = $application;

        return $this;
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
        $connections = $this->connections($channel)
            ->put($connection->identifier(), [
                'connection' => $this->shouldBeSerialized($connection) ? serialize($connection) : $connection,
                'data' => $data,
            ]);

        $this->syncConnections($channel, $connections);
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
        $connections = $this->connections($channel)
            ->reject(fn ($data, $identifier) => (string) $identifier === $connection->identifier());

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
        $key = "{$this->prefix}:channels";

        if ($this->application) {
            $key .= ":{$this->application->id()}";
        }

        return $key;
    }

    /**
     * Get the given channel from the cache.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\SUpport\Collection
     */
    protected function channel(Channel $channel): Collection
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels from the cache.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
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
     * Get the data stored for a connection.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return array
     */
    public function data(Channel $channel, Connection $connection): array
    {
        if (! $connection = $this->connections($channel)->get($connection->identifier())) {
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
