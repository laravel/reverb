<?php

namespace Laravel\Reverb\Channels;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;

class PresenceChannel extends PrivateChannel
{
    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, string $auth = null, string $data = null): void
    {
        parent::subscribe($connection, $auth, $data);

        $this->broadcast(
            $connection->app(),
            [
                'event' => 'pusher_internal:member_added',
                'data' => $data ? json_decode($data, true) : [],
                'channel' => $this->name(),
            ],
            $connection
        );
    }

    /**
     * Unsubscribe from the given channel.
     */
    public function unsubscribe(Connection $connection): void
    {
        $data = App::make(ChannelManager::class)
            ->for($connection->app())
            ->data($this, $connection);

        if (isset($data['user_id'])) {
            $this->broadcast(
                $connection->app(),
                [
                    'event' => 'pusher_internal:member_removed',
                    'data' => ['user_id' => $data['user_id']],
                    'channel' => $this->name(),
                ],
                $connection
            );
        }

        parent::unsubscribe($connection);
    }

    /**
     * Get the data associated with the channel.
     */
    public function data(Application $app): array
    {
        $connections = App::make(ChannelManager::class)
            ->for($app)
            ->connectionKeys($this);

        return [
            'presence' => [
                'count' => $connections->count(),
                'ids' => $connections->map(fn ($connection) => $connection['user_id'])->toArray(),
                'hash' => $connections->keyBy('user_id')->map->user_info->toArray(),
            ],
        ];
    }
}
