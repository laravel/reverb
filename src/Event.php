<?php

namespace Laravel\Reverb;

use Clue\React\Redis\Client;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\Connection;

class Event
{
    /**
     * Dispatch a message to a channel.
     *
     * @param  string  $payload
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public static function dispatch(string $payload, Connection $connection = null): void
    {
        if (! Config::get('reverb.pubsub.enabled')) {
            static::dispatchSynchronously($payload, $connection);

            return;
        }

        $redis = App::make(Client::class);

        $redis->publish(
            Config::get('reverb.pubsub.channel'),
            $payload
        );
    }

    /**
     * Notify all connections subscribed to the given channel.
     *
     * @param  string  $payload
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return void
     */
    public static function dispatchSynchronously(string $payload, Connection $connection = null): void
    {
        $event = json_decode($payload, true);
        $channels = isset($event['channel']) ? [$event['channel']] : $event['channels'];

        foreach ($channels as $channel) {
            $channel = ChannelBroker::create($channel);

            $channel->broadcast($event, $connection);
        }
    }
}
