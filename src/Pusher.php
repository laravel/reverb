<?php

namespace Reverb;

use Exception;
use Illuminate\Support\Str;

class Pusher
{
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

    public static function confirm(Connection $connection)
    {
        self::send($connection, 'connection_established', [
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]);
    }

    public static function subscribe(Connection $connection, string $channel, $auth = ''): void
    {
        self::sendInternally($connection, 'subscription_succeeded', $channel);
    }

    public static function unsubscribe(Connection $connection, string $channel): void
    {
        //
    }

    public static function ping(Connection $connection): void
    {
        static::send($connection, 'pong');
    }

    public static function send(Connection $connection, string $event, array $data = []): void
    {
        $connection->send(json_encode([
            'event' => "pusher:{$event}",
            'data' => json_encode($data),
        ]));
    }

    public static function sendInternally(Connection $connection, string $event, string $channel, array $data = []): void
    {
        $connection->send(json_encode([
            'event' => "pusher_internal:{$event}",
            'data' => json_encode($data),
            'channel' => $channel,
        ]));
    }
}
