<?php

namespace Laravel\Reverb;

use Illuminate\Support\Str;
use Laravel\Reverb\Channels\ChannelBroker;
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

        if (! $channel = $event['channel'] ?? null) {
            return;
        }

        return self::whisper(
            $connection,
            $channel,
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
    public static function whisper(Connection $connection, string $channel, array $payload): void
    {
        ChannelBroker::create($channel)
            ->broadcast($payload, $connection);
    }
}
