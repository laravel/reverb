<?php

namespace Laravel\Reverb\Servers\Reverb\Publishing;

use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use RuntimeException;

class RedisPubSubProvider implements PubSubProvider
{
    protected $publishingClient;

    protected $subscribingClient;

    public function __construct(
        protected RedisClientFactory $clientFactory,
        protected PubSubIncomingMessageHandler $messageHandler,
        protected string $channel,
        protected array $server = []
    ) {
        //
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
     * Disconnect from the publisher.
     */
    public function disconnect(): void
    {
        $this->subscribingClient?->close();
        $this->publishingClient?->close();
    }

    /**
     * Subscribe to the publisher.
     */
    public function subscribe(): void
    {
        $this->ensureConnected();

        $this->subscribingClient->subscribe($this->channel);

        $this->subscribingClient->on('message', function (string $channel, string $payload) {
            $this->messageHandler->handle($payload);
        });
    }

    /**
     * Listen for a given event.
     */
    public function on(string $event, callable $callback): void
    {
        $this->subscribingClient->on('message', function (string $channel, string $payload) use ($event, $callback) {
            $payload = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);

            if (($payload['type'] ?? null) === $event) {
                $callback($payload);
            }
        });
    }

    /**
     * Publish a payload to the publisher.
     */
    public function publish(array $payload): PromiseInterface
    {
        $this->ensureConnected();

        return $this->publishingClient->publish($this->channel, json_encode($payload));
    }

    /**
     * Get the connection URL for Redis.
     */
    protected function redisUrl(): string
    {
        $config = empty($this->server) ? Config::get('database.redis.default') : $this->server;

        $parsed = (new ConfigurationUrlParser)->parseConfiguration($config);

        $driver = strtolower($parsed['driver'] ?? '');

        if (in_array($driver, ['tcp', 'tls'])) {
            $parsed['scheme'] = $driver;
        }

        [$host, $port, $protocol, $query] = [
            $parsed['host'],
            $parsed['port'] ?: 6379,
            Arr::get($parsed, 'scheme') === 'tls' ? 's' : '',
            [],
        ];

        if ($parsed['username'] ?? false) {
            $query['username'] = $parsed['username'];
        }

        if ($parsed['password'] ?? false) {
            $query['password'] = $parsed['password'];
        }

        if ($parsed['database'] ?? false) {
            $query['db'] = $parsed['database'];
        }

        $query = http_build_query($query);

        return "redis{$protocol}://{$host}:{$port}".($query ? "?{$query}" : '');
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
