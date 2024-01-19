<?php

namespace Laravel\Reverb\Servers\Reverb;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSub;
use React\EventLoop\LoopInterface;

class RedisPubSub implements PubSub
{
    use InteractsWithAsyncRedis;

    public function __construct(protected string $channel)
    {
    }

    /**
     * Subscribe to the publisher.
     */
    public function subscribe(LoopInterface $loop): void
    {
        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe($this->channel);

        $redis->on('message', function (string $channel, string $payload) {
            $event = json_decode($payload, true);

            EventDispatcher::dispatchSynchronously(
                unserialize($event['application']),
                $event['payload']
            );
        });
    }

    /**
     * Publish a payload to the publisher.
     */
    public function publish(array $payload): void
    {
        app(Client::class)->publish($this->channel, json_encode($payload));
    }
}
