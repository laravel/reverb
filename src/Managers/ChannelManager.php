<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\ConnectionManager;

class ChannelManager implements ChannelManagerInterface
{
    use EnsuresIntegrity, InteractsWithApplications;

    /**
     * The appliation instance.
     *
     * @var \Laravel\Reverb\Application
     */
    protected $application;

    public function __construct(
        protected Repository $repository,
        protected ConnectionManager $connections,
        protected $prefix = 'reverb'
    ) {
    }

    /**
     * Subscribe to a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Connection  $connection
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
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Connection  $connection
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

        $channels[$channel->name()] = $connections;

        $this->repository->forever($this->key(), $channels);
    }

    /**
     * Get the key for the channels.
     *
     * @return string
     */
    protected function key(): string
    {
        $key = $this->prefix;

        if ($this->application) {
            $key .= ":{$this->application->id()}";
        }

        return $key.':channels';
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
        $channels = $this->repository->get($this->key(), []);

        if ($channel) {
            return collect($channels[$channel->name()] ?? []);
        }

        return collect($channels ?: []);
    }

    /**
     * Get the data stored for a connection.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return array
     */
    public function data(Channel $channel, Connection $connection): array
    {
        if (! $data = $this->connections($channel)->get($connection->identifier())) {
            return [];
        }

        return (array) $data;
    }

    /**
     * Hydrate the connections for the given channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\Support\Collection
     */
    public function hydratedConnections(Channel $channel): Collection
    {
        return $this->connections
            ->for($this->application)
            ->hydrated()
            ->intersectByKeys(
                $this->connections($channel)
            );
    }
}
