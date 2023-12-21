<?php

namespace Laravel\Reverb\Pusher;

use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;

class ClientEvent
{
    /**
     * Handle a pusher event.
     */
    public static function handle(Connection $connection, array $event): ?ClientEvent
    {
        if (! Str::startsWith($event['event'], 'client-')) {
            return null;
        }

        if (! isset($event['channel'])) {
            return null;
        }

        return self::whisper(
            $connection,
            $event
        );
    }

    /**
     * Whisper a message to all connection of the channel.
     */
    public static function whisper(Connection $connection, array $payload): void
    {
        EventDispatcher::dispatch(
            $connection->app(),
            $payload,
            $connection
        );
    }
}
