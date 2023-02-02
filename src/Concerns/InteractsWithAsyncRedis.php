<?php

namespace Laravel\Reverb\Concerns;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Event;
use React\EventLoop\LoopInterface;

trait InteractsWithAsyncRedis
{
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
     * Bind the Redis client to the container.
     */
    protected function bindRedis(LoopInterface $loop): void
    {
        App::singleton(Client::class, function () use ($loop) {
            return (new Factory($loop))->createLazyClient(
                $this->redisUrl()
            );
        });
    }

    /**
     * Subscribe to the Redis pub / sub channel.
     */
    protected function subscribeToRedis(LoopInterface $loop): void
    {
        $config = Config::get('reverb.pubsub');

        if (! $config['enabled']) {
            return;
        }

        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe($config['channel']);

        $redis->on('message', function (string $channel, string $payload) {
            $event = json_decode($payload, true);
            Event::dispatchSynchronously(
                unserialize($event['application']),
                $event['payload']
            );
        });
    }
}
