<?php

namespace Laravel\Reverb\Channels;

use Laravel\Reverb\Application;
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
        if (! $subscription = $this->connections->find($connection)) {
            parent::unsubscribe($connection);

            return;
        }

        if ($userId = $subscription->data('user_id')) {
            $this->broadcast(
                $connection->app(),
                [
                    'event' => 'pusher_internal:member_removed',
                    'data' => ['user_id' => $userId],
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
        $connections = collect($this->connections->all())
            ->map(fn ($connection) => $connection->data());

        return [
            'presence' => [
                'count' => $connections->count(),
                'ids' => $connections->map(fn ($connection) => $connection['user_id'])->all(),
                'hash' => $connections->keyBy('user_id')->map->user_info->toArray(),
            ],
        ];
    }
}
