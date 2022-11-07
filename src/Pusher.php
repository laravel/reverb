<?php

namespace Reverb;

use Exception;
use Illuminate\Support\Str;
use Reverb\Channels\Channel;
use Reverb\Channels\ChannelBroker;

class Pusher
{
    /**
     * Handle a pusher event.
     *
     * @param  \Reverb\Connection  $connection
     * @param  string  $event
     * @param  array  $data
     * @return void
     */
    public static function handle(Connection $connection, string $event, array $payload = []): void
    {
        match (Str::after($event, 'pusher:')) {
            'connection_established' => self::confirm($connection),
            'subscribe' => self::subscribe($connection, $payload['channel'], $payload['auth']),
            'unsubscribe' => self::unsubscribe($connection, $payload['channel']),
            'ping' => self::ping($connection),
            default => throw new Exception('Unknown Pusher event: '.$event),
        };
    }

    /**
     * Confirm the connection.
     *
     * @param  \Reverb\Connection  $connection
     * @return void
     */
    public static function confirm(Connection $connection)
    {
        self::send($connection, 'connection_established', [
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]);
    }

    /**
     * Subscribe to the given channel.
     *
     * @param  \Reverb\Connection  $connection
     * @param  string  $channel
     * @param  string  $auth
     * @return void
     */
    public static function subscribe(Connection $connection, string $channel, $auth = ''): void
    {
        ChannelBroker::create($channel)
            ->subscribe($connection, $auth);

        self::sendInternally($connection, 'subscription_succeeded', $channel);
    }

    /**
     * Unsubscribe from the given channel.
     *
     * @param  \Reverb\Connection  $connection
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
     * @param  \Reverb\Connection  $connection
     */
    public static function ping(Connection $connection): void
    {
        static::send($connection, 'pong');
    }

    /**
     * Send a response to the given connection.
     *
     * @param  \Reverb\Connection  $connection
     * @param  string  $event
     * @param  array  $data
     * @return void
     */
    public static function send(Connection $connection, string $event, array $data = []): void
    {
        $connection->send(json_encode([
            'event' => "pusher:{$event}",
            'data' => json_encode($data),
        ]));
    }

    /**
     * Send an internal response to the given connection.
     *
     * @param  \Reverb\Connection  $connection
     * @param  string  $event
     * @param  string  $channel
     * @param  array  $data
     * @return void
     */
    public static function sendInternally(Connection $connection, string $event, string $channel, array $data = []): void
    {
        $connection->send(json_encode([
            'event' => "pusher_internal:{$event}",
            'data' => json_encode($data),
            'channel' => $channel,
        ]));
    }

    public static function error(Connection $connection)
    {
        $connection->send(json_encode([
            'event' => 'pusher:error',
            'data' => [
                // 'message' => $this->getMessage(),
                // 'code' => $this->getCode(),
            ],
        ]));
    }
}
