<?php

namespace Reverb;

use Clue\React\Redis\Client;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Reverb\Channels\ChannelBroker;
use Reverb\Contracts\ChannelManager;

class PubSub
{
    /**
     * Publish a message to a channel.
     *
     * @param  string  $payload
     * @return void
     */
    public static function publish(string $payload): void
    {
        if (! Config::get('reverb.pubsub.enabled')) {
            static::notify($payload);

            return;
        }

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
    public static function notify(string $payload): void
    {
        $event = json_decode($payload, true);
        $channels = isset($event['channel']) ? [$event['channel']] : $event['channels'];

        foreach ($channels as $channel) {
            $channel = ChannelBroker::create($channel);

            App::make(ChannelManager::class)
                ->broadcast($channel, [
                    'event' => $event['name'],
                    'channel' => $channel->name(),
                    'data' => $event['data'],
                ]);
        }
    }
}
