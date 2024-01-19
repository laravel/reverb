<?php

namespace Laravel\Reverb\Servers\Reverb;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSub;
use React\EventLoop\LoopInterface;
use RuntimeException;

class RedisPubSub implements PubSub
{
    use InteractsWithAsyncRedis;

    protected $publishingClient;
    protected $subscribingClient;

    public function __construct(protected RedisClientFactory $clientFactory,
                                protected string $channel)
    {
    }

    /**
     * Connect to the publisher.
     */
    public function connect(LoopInterface $loop): void
    {
        $this->publishingClient = $this->clientFactory->make($loop, $this->redisUrl());
        $this->subscribingClient = $this->clientFactory->make($loop, $this->redisUrl());
    }

    /**
     * Subscribe to the publisher.
     */
    public function subscribe(): void
    {
        $this->ensureConnected();

        $this->subscribingClient->subscribe($this->channel);

        $this->subscribingClient->on('message', function (string $channel, string $payload) {
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
        $this->ensureConnected();

        $this->publishingClient->publish($this->channel, json_encode($payload));
    }

    /**
     * Ensure that a connection to Redis has been established.
     */
    protected function ensureConnected(): void
    {
        if (! $this->publishingClient) {
            throw new RuntimeException("Connection to Redis has not been established.");
        }
    }
}
