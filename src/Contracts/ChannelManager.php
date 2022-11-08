<?php

namespace Reverb\Contracts;

use Illuminate\Support\Collection;
use Reverb\Channels\Channel;
use Reverb\Contracts\Connection;

interface ChannelManager
{
    /**
     * Subscribe to a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Contracts\Connection  $connection
     * @param  array  $data
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection, $data = []): void;

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Contracts\Connection  $connection
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
     * @param  \Reverb\Contracts\Connection  $connection
     * @return void;
     */
    public function unsubscribeFromAll(Connection $connection): void;

    /**
     * Get all connections subscribed to a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @return \Illuminate\Support\Collection
     */
    public function connections(Channel $channel): Collection;

    /**
     * Send a message to all connections subscribed to the given channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  array  $payload
     * @return void
     */
    public function broadcast(Channel $channel, array $payload = []): void;
}
