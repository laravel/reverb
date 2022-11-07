<?php

namespace Reverb\Contracts;

use Reverb\Channels\Channel;
use Reverb\Connection;
use Traversable;

interface ChannelManager
{
    /**
     * Subscribe to a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function subscribe(Channel $channel, Connection $connection): void;

    /**
     * Unsubscribe from a channel.
     *
     * @param  \Reverb\Channels\Channel  $channel
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public function unsubscribe(Channel $channel, Connection $connection): void;

    /**
     * Get all the channels.
     *
     * @return \Traversable
     */
    public function all(): Traversable;

    /**
     * Unsubscribe from all channels.
     *
     * @return void;
     */
    public function unsubscribeFromAll(Connection $connection): void;

    /**
     * Get all connections subscribed to a channel.
     *
     * @return \Traversable
     */
    public function connections(Channel $channel): Traversable;
}
