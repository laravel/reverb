<?php

namespace Laravel\Reverb\Contracts;

use Illuminate\Support\Collection;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Managers\Connections;

interface ChannelManager
{
    /**
     * Get the application instance.
     */
    public function app(): ?Application;

    /**
     * The application the channel manager should be scoped to.
     */
    public function for(Application $application): ChannelManager;

    /**
     * Subscribe to a channel.
     */
    public function subscribe(Channel $channel, Connection $connection, array $data = []): void;

    /**
     * Unsubscribe from a channel.
     */
    public function unsubscribe(Channel $channel, Connection $connection): void;

    /**
     * Get all the channels.
     */
    public function all(): Collection;

    /**
     * Unsubscribe from all channels.
     */
    public function unsubscribeFromAll(Connection $connection): void;

    /**
     * Get all connection keys for the given channel.
     */
    public function connectionKeys(Channel $channel): Collection;

    /**
     * Get all connections for the given channel.
     *
     * @return \Laravel\Reverb\Managers\Connections|\Laravel\Reverb\Connection[]|string[]
     */
    public function connections(Channel $channel): Connections;

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void;
}
