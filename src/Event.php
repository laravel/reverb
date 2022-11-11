<?php

namespace Reverb;

use Clue\React\Redis\Client;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Reverb\Channels\ChannelBroker;

class Event
{
    /**
     * Dispatch a message to a channel.
     *
     * @param  string  $payload
     * @return void
     */
    public static function dispatch(string $payload): void
    {
        if (! Config::get('reverb.pubsub.enabled')) {
            dump('Not asynchronous');
            static::dispatchSynchronously($payload);

            return;
        }

        dump('Sending pubsub message');

        App::make(Client::class)->publish(
            Config::get('reverb.pubsub.channel'),
            $payload
        );
    }

    /**
     * Notify all connections subscribed to the given channel.
     *
     * @param  string  $payload
     * @return void
     */
    public static function dispatchSynchronously(string $payload): void
    {
        $event = json_decode($payload, true);
        $channels = isset($event['channel']) ? [$event['channel']] : $event['channels'];

        foreach ($channels as $channel) {
            $channel = ChannelBroker::create($channel);

            $channel->broadcast([
                'event' => $event['name'],
                'channel' => $channel->name(),
                'data' => $event['data'],
            ]);
        }
    }
}
