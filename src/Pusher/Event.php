<?php

namespace Laravel\Reverb\Pusher;

use Exception;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;

class Event
{
    /**
     * Handle a pusher event.
     */
    public static function handle(Connection $connection, string $event, array $payload = []): void
    {
        match (Str::after($event, 'pusher:')) {
            'connection_established' => self::acknowledge($connection),
            'subscribe' => self::subscribe(
                $connection,
                $payload['channel'],
                $payload['auth'] ?? null,
                $payload['channel_data'] ?? null
            ),
            'unsubscribe' => self::unsubscribe($connection, $payload['channel']),
            'ping' => self::pong($connection),
            'pong' => $connection->touch(),
            default => throw new Exception('Unknown Pusher event: '.$event),
        };
    }

    /**
     * Acknowledge the connection.
     */
    public static function acknowledge(Connection $connection): void
    {
        self::send($connection, 'connection_established', [
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]);
    }

    /**
     * Subscribe to the given channel.
     */
    public static function subscribe(Connection $connection, string $channel, string $auth = null, string $data = null): void
    {
        $channel = app(ChannelManager::class)
            ->for($connection->app())
            ->find($channel);

        $channel->subscribe($connection, $auth, $data);

        self::sendInternally($connection, 'subscription_succeeded', $channel->name(), $channel->data());
    }

    /**
     * Unsubscribe from the given channel.
     */
    public static function unsubscribe(Connection $connection, string $channel): void
    {
        $channel = app(ChannelManager::class)
            ->for($connection->app())
            ->find($channel)
            ->unsubscribe($connection);
    }

    /**
     * Respond to a ping.
     */
    public static function pong(Connection $connection): void
    {
        static::send($connection, 'pong');
    }

    /**
     * Send a ping.
     */
    public static function ping(Connection $connection): void
    {
        static::send($connection, 'ping');
    }

    /**
     * Send a response to the given connection.
     */
    public static function send(Connection $connection, string $event, array $data = []): void
    {
        $connection->send(
            static::formatPayload($event, $data)
        );
    }

    /**
     * Send an internal response to the given connection.
     */
    public static function sendInternally(Connection $connection, string $event, string $channel, array $data = []): void
    {
        $connection->send(
            static::formatInternalPayload($event, $data, $channel)
        );
    }

    /**
     * Format the payload for the given event.
     */
    public static function formatPayload(string $event, array $data = [], string $channel = null, string $prefix = 'pusher:'): string|false
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
    public static function formatInternalPayload(string $event, array $data = [], $channel = null): string|false
    {
        return static::formatPayload($event, $data, $channel, 'pusher_internal:');
    }
}
