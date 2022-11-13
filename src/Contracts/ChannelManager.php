<?php

namespace Laravel\Reverb\Contracts;

use Illuminate\Support\Collection;
use Laravel\Reverb\Channels\Channel;

interface ChannelManager
{
    /**
     * Subscribe to a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  array  $data
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection, $data = []): void;

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
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
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void;
     */
    public function unsubscribeFromAll(Connection $connection): void;

    /**
     * Get all connections subscribed to a channel.
     *
     * @param  \Laravel\Reverb\Channels\Channel  $channel
     * @return \Illuminate\Support\Collection
     */
    public function connections(Channel $channel): Collection;
}
