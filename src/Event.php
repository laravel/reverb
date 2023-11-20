<?php

namespace Laravel\Reverb;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\ServerProvider;

class Event
{
    /**
     * Dispatch a message to a channel.
     */
    public static function dispatch(Application $app, array $payload, Connection $connection = null): void
    {
        $server = App::make(ServerProvider::class);

        if ($server->shouldNotPublishEvents()) {
            static::dispatchSynchronously($app, $payload, $connection);

            return;
        }

        $server->publish([
            'application' => serialize($app),
            'payload' => $payload,
        ]);
    }

    /**
     * Notify all connections subscribed to the given channel.
     */
    public static function dispatchSynchronously(Application $app, array $payload, Connection $connection = null): void
    {
        $channels = Arr::wrap($payload['channels'] ?? $payload['channel'] ?? []);

        foreach ($channels as $channel) {
            unset($payload['channels']);
            $channel = app(ChannelManager::class)->find($channel);
            $payload['channel'] = $channel->name();

            $channel->broadcast($app, $payload, $connection);
        }
    }
}
