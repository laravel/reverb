<?php

namespace Laravel\Reverb\Protocols\Pusher\Channels\Concerns;

use Laravel\Reverb\Contracts\Connection;

trait InteractsWithPresenceChannels
{
    use InteractsWithPrivateChannels;

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);

        parent::subscribe($connection, $auth, $data);

        parent::broadcastInternally(
            [
                'event' => 'pusher_internal:member_added',
                'data' => $data ? json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR) : [],
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
            parent::broadcast(
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
    public function data(): array
    {
        $connections = collect($this->connections->all())
            ->map(fn ($connection) => $connection->data());

        if ($connections->contains(fn ($connection) => ! isset($connection['user_id']))) {
            return [
                'presence' => [
                    'count' => 0,
                    'ids' => [],
                    'hash' => [],
                ],
            ];
        }

        return [
            'presence' => [
                'count' => $connections->count() ?? 0,
                'ids' => $connections->map(fn ($connection) => $connection['user_id'])->values()->all(),
                'hash' => $connections->keyBy('user_id')->map->user_info->toArray(),
            ],
        ];
    }
}
