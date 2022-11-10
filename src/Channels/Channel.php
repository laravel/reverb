<?php

namespace Reverb\Channels;

use Illuminate\Support\Facades\App;
use Reverb\Contracts\ChannelManager;
use Reverb\Contracts\Connection;

class Channel
{
    public function __construct(protected string $name)
    {
    }

    /**
     * Get the channel name.
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Subscribe to the given channel.
     *
     * @param  \Reverb\Contracts\Connection  $connection
     * @param  string|null  $auth
     * @param  string|null  $data
     * @return bool
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        App::make(ChannelManager::class)
            ->subscribe($this, $connection, $data ? json_decode($data, true) : []);
    }

    /**
     * Unsubscribe from the given channel.
     *
     * @param  \Reverb\Contracts\Connection  $connection
     * @return bool
     */
    public function unsubscribe(Connection $connection): void
    {
        App::make(ChannelManager::class)
            ->unsubscribe($this, $connection);
    }

    /**
     * Send a message to all connections subscribed to the channel.
     *
     * @param  array  $payload
     * @return void
     */
    public function broadcast(array $payload = [])
    {
        App::make(ChannelManager::class)
            ->connections($this)->each(function ($data, $identifier) use ($payload) {
                if (! $connection = $this->connections->get($identifier)) {
                    return;
                }

                $connection->send(json_encode($payload));
            });
    }

    /**
     * Get the data associated with the channel.
     *
     * @return array
     */
    public function data()
    {
        return [];
    }
}
