<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Protocols\Pusher\Channels\CacheChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Concerns\InteractsWithChannelInformation;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\ServerProviderManager;
use Throwable;

class EventHandler
{
    use InteractsWithChannelInformation;

    /**
     * Create a new Pusher event instance.
     */
    public function __construct(protected ChannelManager $channels)
    {
        //
    }

    /**
     * Handle an incoming Pusher event.
     */
    public function handle(Connection $connection, string $event, array $payload = []): void
    {
        $event = Str::after($event, 'pusher:');

        match ($event) {
            'connection_established' => $this->acknowledge($connection),
            'subscribe' => $this->subscribe(
                $connection,
                $payload['channel'],
                $payload['auth'] ?? null,
                $payload['channel_data'] ?? null
            ),
            'unsubscribe' => $this->unsubscribe($connection, $payload['channel']),
            'ping' => $this->pong($connection),
            'pong' => $connection->touch(),
            default => throw new Exception('Unknown Pusher event: '.$event),
        };
    }

    /**
     * Acknowledge the connection.
     */
    public function acknowledge(Connection $connection): void
    {
        $this->send($connection, 'connection_established', [
            'socket_id' => $connection->id(),
            'activity_timeout' => $connection->app()->activityTimeout(),
        ]);
    }

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, string $channel, ?string $auth = null, ?string $data = null): void
    {
        Validator::make([
            'channel' => $channel,
            'auth' => $auth,
            'channel_data' => $data,
        ], [
            'channel' => ['nullable', 'string'],
            'auth' => ['nullable', 'string'],
            'channel_data' => ['nullable', 'json'],
        ])->validate();

        if (Str::length($channel) > 164) {
            $this->sendSubscriptionError($connection, $channel, 'InvalidChannelName', 'Channel name is too long');

            return;
        }

        if (! $connection->canSubscribeTo($channel)) {
            $this->sendSubscriptionError($connection, $channel, 'LimitReached', 'Connection is over channel quota');

            return;
        }

        $channels = $this->channels->for($connection->app());

        $channel = $channels->findOrCreate($channel);

        try {
            $channel->subscribe($connection, $auth, $data);
        } catch (Throwable $e) {
            if ($channel->connections() === []) {
                $channels->remove($channel);
            }

            throw $e;
        }

        $this->afterSubscribe($channel, $connection);
    }

    /**
     * Send a subscription error for the given channel.
     */
    protected function sendSubscriptionError(Connection $connection, string $channel, string $type, string $error): void
    {
        $this->send($connection, 'subscription_error', [
            'type' => $type,
            'error' => $error,
            'status' => 4300,
        ], $channel);
    }

    /**
     * Carry out any actions that should be performed after a subscription.
     */
    protected function afterSubscribe(Channel $channel, Connection $connection): void
    {
        if ($this->isPresenceChannel($channel) && app(ServerProviderManager::class)->subscribesToEvents()) {
            app(MetricsHandler::class)
                ->gather($connection->app(), 'presence_data', ['channel' => $channel->name()])
                ->catch(fn () => $channel->data())
                ->then(fn (array $data) => $this->sendSubscriptionSucceeded($channel, $connection, $data));

            return;
        }

        $this->sendSubscriptionSucceeded($channel, $connection, $channel->data());
    }

    /**
     * Send the subscription succeeded payload for the given channel.
     */
    protected function sendSubscriptionSucceeded(Channel $channel, Connection $connection, array $data): void
    {
        $this->sendInternally($connection, 'subscription_succeeded', $data, $channel->name());

        match (true) {
            $channel instanceof CacheChannel => $this->sendCachedPayload($channel, $connection),
            default => null,
        };
    }

    /**
     * Unsubscribe from the given channel.
     */
    public function unsubscribe(Connection $connection, string $channel): void
    {
        $channel = $this->channels
            ->for($connection->app())
            ->find($channel)
            ?->unsubscribe($connection);
    }

    /**
     * Send the cached payload for the given channel.
     */
    protected function sendCachedPayload(CacheChannel $channel, Connection $connection): void
    {
        if ($channel->hasCachedPayload()) {
            $connection->send(
                json_encode($channel->cachedPayload())
            );

            return;
        }

        $this->send($connection, 'cache_miss', channel: $channel->name());
    }

    /**
     * Respond to a ping on the given connection.
     */
    public function pong(Connection $connection): void
    {
        static::send($connection, 'pong');
    }

    /**
     * Send a ping to the given connection.
     */
    public function ping(Connection $connection): void
    {
        $connection->usesControlFrames()
            ? $connection->control()
            : static::send($connection, 'ping');

        $connection->ping();
    }

    /**
     * Send a response to the given connection.
     */
    public function send(Connection $connection, string $event, array $data = [], ?string $channel = null): void
    {
        $connection->send(
            static::formatPayload($event, $data, $channel)
        );
    }

    /**
     * Send an internal response to the given connection.
     */
    public function sendInternally(Connection $connection, string $event, array $data = [], ?string $channel = null): void
    {
        $connection->send(
            static::formatInternalPayload($event, $data, $channel)
        );
    }

    /**
     * Format the payload for the given event.
     */
    public function formatPayload(string $event, array $data = [], ?string $channel = null, string $prefix = 'pusher:'): string|false
    {
        return json_encode(
            array_filter([
                'event' => $prefix.$event,
                'data' => empty($data) ? null : json_encode($data),
                'channel' => $channel,
            ])
        );
    }

    /**
     * Format the internal payload for the given event.
     */
    public function formatInternalPayload(string $event, array $data = [], $channel = null): string|false
    {
        return json_encode(
            array_filter([
                'event' => 'pusher_internal:'.$event,
                'data' => json_encode((object) $data),
                'channel' => $channel,
            ])
        );
    }
}
