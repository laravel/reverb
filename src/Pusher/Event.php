<?php

namespace Laravel\Reverb\Pusher;

use Exception;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;

class Event
{
    public function __construct(protected ChannelManager $channels)
    {
        //
    }

    /**
     * Handle a pusher event.
     */
    public function handle(Connection $connection, string $event, array $payload = []): void
    {
        match (Str::after($event, 'pusher:')) {
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
            'activity_timeout' => 30,
        ]);
    }

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, string $channel, string $auth = null, string $data = null): void
    {
        $channel = $this->channels
            ->for($connection->app())
            ->find($channel);

        $channel->subscribe($connection, $auth, $data);

        $this->sendInternally($connection, 'subscription_succeeded', $channel->name(), $channel->data());
    }

    /**
     * Unsubscribe from the given channel.
     */
    public function unsubscribe(Connection $connection, string $channel): void
    {
        $channel = $this->channels
            ->for($connection->app())
            ->find($channel)
            ->unsubscribe($connection);
    }

    /**
     * Respond to a ping.
     */
    public function pong(Connection $connection): void
    {
        static::send($connection, 'pong');
    }

    /**
     * Send a ping.
     */
    public function ping(Connection $connection): void
    {
        static::send($connection, 'ping');
        
        $connection->ping();
    }

    /**
     * Send a response to the given connection.
     */
    public function send(Connection $connection, string $event, array $data = []): void
    {
        $connection->send(
            static::formatPayload($event, $data)
        );
    }

    /**
     * Send an internal response to the given connection.
     */
    public function sendInternally(Connection $connection, string $event, string $channel, array $data = []): void
    {
        $connection->send(
            static::formatInternalPayload($event, $data, $channel)
        );
    }

    /**
     * Format the payload for the given event.
     */
    public function formatPayload(string $event, array $data = [], string $channel = null, string $prefix = 'pusher:'): string|false
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
        return static::formatPayload($event, $data, $channel, 'pusher_internal:');
    }
}
