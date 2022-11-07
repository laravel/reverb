<?php

namespace Reverb\Managers\Channels;

use Illuminate\Support\Collection as IlluminateCollection;
use Reverb\Channels\Channel;
use Reverb\Channels\ChannelBroker;
use Reverb\Connection;
use Reverb\Contracts\ChannelManager;
use Traversable;

class Collection implements ChannelManager
{
    /**
     * The channels.
     *
     * @var @var \Illuminate\Support\Collection<\Reverb\Connection>
     */
    protected $channels;

    public function __construct()
    {
        $this->channels = new IlluminateCollection;
    }

    /**
     * Subscribe to a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection): void
    {
        $connections = $this->connections($channel)
            ->push($connection->identifier());

        $this->channels->put($channel->name(), $connections);
    }

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function unsubscribe(Channel $channel, Connection $connection): void
    {
        $connections = $this->connections($channel)
            ->reject(fn ($identifier) => $identifier === $connection->identifier());

        $this->channels->put($channel->name(), $connections);
    }

    /**
     * Get all the channels.
     *
     * @return \Traversable
     */
    public function all(): Traversable
    {
        return $this->channels->map(function ($connections, $name) {
            return ChannelBroker::create($name);
        })->values();
    }

    /**
     * Unsubscribe from all channels.
     *
     * @return void
     */
    public function unsubscribeFromAll(Connection $connection): void
    {
        $this->channels->each(function ($connections, $name) use ($connection) {
            $this->unsubscribe(
                ChannelBroker::create($name),
                $connection
            );
        });
    }

    /**
     * Get all connections subscribed to a channel.
     *
     * @return \Traversable
     */
    public function connections(Channel $channel): Traversable
    {
        $connections = $this->channels->first(fn ($value, $key) => $key === $channel->name(), collect());

        return collect($connections->toArray());
    }
}
