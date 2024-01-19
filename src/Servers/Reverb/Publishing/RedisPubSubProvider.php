<?php

namespace Laravel\Reverb\Servers\Reverb\Publishing;

use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\EventLoop\LoopInterface;
use RuntimeException;

class RedisPubSubProvider implements PubSubProvider
{
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
     * Get the connection URL for Redis.
     */
    protected function redisUrl(): string
    {
        $config = Config::get('database.redis.default');

        $host = $config['host'];
        $port = $config['port'] ?: 6379;

        $query = [];

        if ($config['password']) {
            $query['password'] = $config['password'];
        }

        if ($config['database']) {
            $query['db'] = $config['database'];
        }

        $query = http_build_query($query);

        return "redis://{$host}:{$port}".($query ? "?{$query}" : '');
    }

    /**
     * Ensure that a connection to Redis has been established.
     */
    protected function ensureConnected(): void
    {
        if (! $this->publishingClient) {
            throw new RuntimeException('Connection to Redis has not been established.');
        }
    }
}
