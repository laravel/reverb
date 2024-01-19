<?php

namespace Laravel\Reverb\Concerns;

use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\ServerServiceProviderManager;

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
     * Subscribe to the Redis pub / sub channel.
     */
    protected function subscribeToRedis(): void
    {
        $server = app(ServerServiceProviderManager::class);

        if ($server->doesNotSubscribeToEvents()) {
            return;
        }

        app(PubSubProvider::class)->subscribe();
    }
}
