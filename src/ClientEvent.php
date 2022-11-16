<?php

namespace Laravel\Reverb;

use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;

class ClientEvent
{
    /**
     * Handle a pusher event.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  array  $event
     */
    public static function handle(Connection $connection, array $event)
    {
        if (! Str::startsWith($event['event'], 'client-')) {
            return;
        }

        if (! isset($event['channel'])) {
            return;
        }

        return self::whisper(
            $connection,
            $event
        );
    }

    /**
     * Whisper a message to all connection of the channel.
     *
     * @param  Connection  $connection
     * @param  string  $channel
     * @param  array  $payload
     * @return void
     */
    public static function whisper(Connection $connection, array $payload): void
    {
        Event::dispatch(
            json_encode($payload),
            $connection
        );
    }
}
