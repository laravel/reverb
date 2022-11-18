<?php

namespace Laravel\Reverb\Channels;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;

class PresenceChannel extends PrivateChannel
{
    /**
     * Subscribe to the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $auth
     * @param  string  $data
     * @return bool
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        parent::subscribe($connection, $auth, $data);

        $this->broadcast([
            'event' => 'pusher_internal:member_added',
            'data' => $data ? json_decode($data, true) : [],
            'channel' => $this->name(),
        ]);
    }

    /**
     * Unsubscribe from the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return bool
     */
    public function unsubscribe(Connection $connection): void
    {
        $data = App::make(ChannelManager::class)
            ->data($this, $connection);

        if (isset($data['user_id'])) {
            $this->broadcast([
                'event' => 'pusher_internal:member_removed',
                'data' => ['user_id' => $data['user_id']],
                'channel' => $this->name(),
            ]);
        }

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
            ->connections($this)
            ->map(function ($connection) {
                return $connection['data'];
            });

        return [
            'presence' => [
                'count' => $connections->count(),
                'ids' => $connections->map(fn ($connection) => $connection['user_id'])->toArray(),
                'hash' => $connections->keyBy('user_id')->map->user_info->toArray(),
            ],
        ];
    }
}
