<?php

namespace Laravel\Reverb\Protocols\Pusher;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;

class ClientEvent
{
    /**
     * Handle a Pusher client event.
     */
    public static function handle(Connection $connection, array $event): ?ClientEvent
    {
        Validator::make($event, [
            'event' => ['required', 'string'],
            'channel' => ['required', 'string'],
            'data' => ['nullable', 'array'],
        ])->validate();

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
     * Whisper a message to all connections on the channel associated with the event.
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
