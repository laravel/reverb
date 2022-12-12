<?php

namespace Laravel\Reverb\Contracts;

use Illuminate\Support\Collection;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Connection;

interface ChannelManager
{
    /**
     * Get the application instance.
     *
     * @return Application|null
     */
    public function app(): ?Application;
    
    /**
     * The application the channel manager should be scoped to.
     *
     * @param  \Laravel\Reverb\Application  $application
     * @return \Laravel\Reverb\Contracts\ChannelManager
     */
    public function for(Application $application): ChannelManager;

    /**
     * Subscribe to a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Connection  $connection
     * @param  array  $data
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection, $data = []): void;

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Connection  $connection
     * @return void
     */
    public function unsubscribe(Channel $channel, Connection $connection): void;

    /**
     * Get all the channels.
     *
     * @return \Illuminate\Support\Collection
     */
    public function all(): Collection;

    /**
     * Unsubscribe from all channels.
     *
     * @param  \Laravel\Reverb\Connection  $connection
     * @return void;
     */
    public function unsubscribeFromAll(Connection $connection): void;

    /**
     * Get all connection keys for the given channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\Support\Collection
     */
    public function connectionKeys(Channel $channel): Collection;

    /**
     * Get all connections for the given channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\Support\Collection
     */
    public function connections(Channel $channel): Collection;

    /**
     * Flush the channel manager repository.
     *
     * @return void
     */
    public function flush(): void;
}
