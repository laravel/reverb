<?php

namespace Reverb\Channels;

use Illuminate\Support\Facades\App;
use Reverb\Contracts\ChannelManager;
use Reverb\Contracts\Connection;

class PresenceChannel extends PrivateChannel
{
    /**
     * Subscribe to the given channel.
     *
     * @param  \Reverb\Contracts\Connection  $connection
     * @param  string  $auth
     * @param  string  $data
     * @return bool
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        parent::subscribe($connection, $auth, $data);

        App::make(ChannelManager::class)
            ->broadcast($this, [
                'event' => 'pusher_internal:member_added',
                'data' => $data ? json_decode($data, true) : [],
                'channel' => $this->name(),
            ]);
    }

    /**
     * Unsubscribe from the given channel.
     *
     * @param  \Reverb\Contracts\Connection  $connection
     * @return bool
     */
    public function unsubscribe(Connection $connection): void
    {
        $data = App::make(ChannelManager::class)
            ->data($this, $connection);

        App::make(ChannelManager::class)
            ->broadcast($this, [
                'event' => 'pusher_internal:member_removed',
                'data' => ['user_id' => $data['user_id']],
                'channel' => $this->name(),
            ]);

        parent::unsubscribe($connection);
    }

    /**
     * Get the data associated with the channel.
     *
     * @return array
     */
    public function data()
    {
        $connections = App::make(ChannelManager::class)
            ->connections($this);

        return [
            'presence' => [
                'count' => $connections->count(),
                'ids' => $connections->map->user_id->values(),
                'hash' => $connections->keyBy('user_id')->map->user_info,
            ],
        ];
    }
}
