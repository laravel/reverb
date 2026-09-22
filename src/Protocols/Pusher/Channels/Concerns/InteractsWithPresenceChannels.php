<?php

namespace Laravel\Reverb\Protocols\Pusher\Channels\Concerns;

use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Protocols\Pusher\MetricsHandler;
use Laravel\Reverb\ServerProviderManager;
use React\Promise\PromiseInterface;

trait InteractsWithPresenceChannels
{
    use InteractsWithPrivateChannels;

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        $this->verify($connection, $auth, $data);

        $userData = $data ? json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR) : [];

        $userId = $userData['user_id'] ?? null;

        if ($this->userIsSubscribed($userId)) {
            parent::subscribe($connection, $auth, $data);

            return;
        }

        parent::subscribe($connection, $auth, $data);

        if ($userId && app(ServerProviderManager::class)->subscribesToEvents()) {
            $this->userConnections($connection, $userId)->then(function (array $connections) use ($connection, $userData) {
                if ($this->hasEarlierConnection($connections, $connection)) {
                    return;
                }

                $this->broadcastMemberAdded($connection, $userData);
            });

            return;
        }

        $this->broadcastMemberAdded($connection, $userData);
    }

    /**
     * Notify the channel the given user has been added.
     */
    protected function broadcastMemberAdded(Connection $connection, array $userData): void
    {
        EventDispatcher::dispatch(
            $connection->app(),
            [
                'event' => 'pusher_internal:member_added',
                'data' => json_encode((object) $userData),
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
        $subscription = $this->connections->find($connection);

        parent::unsubscribe($connection);

        $userId = $subscription?->data('user_id');

        if (! $userId || $this->userIsSubscribed($userId)) {
            return;
        }

        if (app(ServerProviderManager::class)->subscribesToEvents()) {
            $this->userConnections($connection, $userId)->then(function (array $connections) use ($connection, $userId) {
                if ($connections === []) {
                    $this->broadcastMemberRemoved($connection, $userId);
                }
            });

            return;
        }

        $this->broadcastMemberRemoved($connection, $userId);
    }

    /**
     * Notify the channel the given user has been removed.
     */
    protected function broadcastMemberRemoved(Connection $connection, int|string $userId): void
    {
        EventDispatcher::dispatch(
            $connection->app(),
            [
                'event' => 'pusher_internal:member_removed',
                'data' => json_encode(['user_id' => $userId]),
                'channel' => $this->name(),
            ],
            $connection
        );
    }

    /**
     * Get the given user's connections to the channel across all servers.
     */
    protected function userConnections(Connection $connection, int|string $userId): PromiseInterface
    {
        return app(MetricsHandler::class)->gather(
            $connection->app(),
            'presence_connections',
            ['channel' => $this->name(), 'user_id' => $userId]
        )->catch(fn () => []);
    }

    /**
     * Determine if the user has an earlier connection than the one given on any server.
     */
    protected function hasEarlierConnection(array $connections, Connection $connection): bool
    {
        $earliest = collect($connections)->sortBy([['subscribed_at', 'asc'], ['id', 'asc']])->first();

        return isset($earliest['id']) && $earliest['id'] !== $connection->id();
    }

    /**
     * Get the data associated with the channel.
     */
    public function data(): array
    {
        $connections = collect($this->connections->all())
            ->map(fn ($connection) => $connection->data())
            ->unique('user_id');

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
                'hash' => $connections->pluck('user_info', 'user_id')->map(fn ($info) => $info ?? (object) [])->all(),
            ],
        ];
    }

    /**
     * Determine if the given user is subscribed to the channel.
     */
    protected function userIsSubscribed(?string $userId): bool
    {
        if (! $userId) {
            return false;
        }

        return collect($this->connections->all())->map(fn ($connection) => (string) $connection->data('user_id'))->contains($userId);
    }
}
