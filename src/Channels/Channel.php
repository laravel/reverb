<?php

namespace Reverb\Channels;

use Illuminate\Support\Facades\App;
use Reverb\Connection;
use Reverb\Contracts\ChannelManager;

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
     * @param  Connection  $connection
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
     * @param  Connection  $connection
     * @return bool
     */
    public function unsubscribe(Connection $connection): void
    {
        App::make(ChannelManager::class)
            ->unsubscribe($this, $connection);
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
