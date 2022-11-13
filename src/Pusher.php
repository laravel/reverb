<?php

namespace Laravel\Reverb;

use Exception;
use Illuminate\Support\Str;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\Connection;

class Pusher
{
    /**
     * Handle a pusher event.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $event
     * @param  array  $data
     * @return void
     */
    public static function handle(Connection $connection, string $event, array $payload = []): void
    {
        match (Str::after($event, 'pusher:')) {
            'connection_established' => self::acknowledge($connection),
            'subscribe' => self::subscribe(
                $connection,
                $payload['channel'],
                $payload['auth'],
                $payload['channel_data'] ?? null
            ),
            'unsubscribe' => self::unsubscribe($connection, $payload['channel']),
            'ping' => self::ping($connection),
            default => throw new Exception('Unknown Pusher event: '.$event),
        };
    }

    /**
     * Acknowledge the connection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public static function acknowledge(Connection $connection)
    {
        self::send($connection, 'connection_established', [
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]);
    }

    /**
     * Subscribe to the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $channel
     * @param  string  $auth
     * @return void
     */
    public static function subscribe(Connection $connection, string $channel, string $auth, ?string $data = null): void
    {
        $channel = ChannelBroker::create($channel);

        $channel->subscribe($connection, $auth, $data);

        self::sendInternally($connection, 'subscription_succeeded', $channel->name(), $channel->data());
    }

    /**
     * Unsubscribe from the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $channel
     * @return void
     */
    public static function unsubscribe(Connection $connection, string $channel): void
    {
        ChannelBroker::create($channel)
            ->unsubscribe($connection);
    }

    /**
     * Respond to a ping.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     */
    public static function ping(Connection $connection): void
    {
        static::send($connection, 'pong');
    }

    /**
     * Send a response to the given connection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $event
     * @param  array  $data
     * @return void
     */
    public static function send(Connection $connection, string $event, array $data = []): void
    {
        $connection->send(
            static::formatPayload($event, $data)
        );
    }

    /**
     * Send an internal response to the given connection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string  $event
     * @param  string  $channel
     * @param  array  $data
     * @return void
     */
    public static function sendInternally(Connection $connection, string $event, string $channel, array $data = []): void
    {
        $connection->send(
            static::formatInternalPayload($event, $data, $channel)
        );
    }

    /**
     * Send an error response to the given connection.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public static function error(Connection $connection)
    {
        $connection->send(
            static::formatPayload('pusher:error', [
                'data' => [],
            ])
        );
    }

    /**
     * Format the payload for the given event.
     *
     * @param  string  $event
     * @param  array  $data
     * @param  string  $channel
     * @param  string  $prefix
     * @return string|false
     */
    public static function formatPayload(string $event, array $data = [], ?string $channel = null, string $prefix = 'pusher:')
    {
        return json_encode(
            array_filter([
                'event' => $prefix.$event,
                'data' => json_encode($data),
                'channel' => $channel,
            ])
        );
    }

    /**
     * Format the internal payload for the given event.
     *
     * @param  string  $event
     * @param  array  $data
     * @param  string  $channel
     * @return string|false
     */
    public static function formatInternalPayload(string $event, array $data = [], $channel = null)
    {
        return static::formatPayload($event, $data, $channel, 'pusher_internal:');
    }
}
