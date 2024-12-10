<?php

namespace Laravel\Reverb\Servers\Reverb\Publishing;

use Clue\React\Redis\Client;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Loggers\Log;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;
use RuntimeException;

class RedisClient
{
    /**
     * Redis connection client.
     *
     * @var \Clue\React\Redis\Client
     */
    protected $client;

    /**
     * Number of seconds the elapsed since attempting to reconnect.
     */
    protected int $clientReconnectionTimer = 0;

    /**
     * Determine if the client should attempt to reconnect when disconnected from the server.
     */
    protected bool $shouldReconnect = true;

    /**
     * Subscription events queued during while disconnected from Redis.
     */
    protected $queuedSubscriptionEvents = [];

    /**
     * Publish events queued during while disconnected from Redis.
     */
    protected $queuedPublishEvents = [];

    /**
     * Create a new instance of the Redis client.
     *
     * @param  callable|null  $onConnect
     */
    public function __construct(
        protected LoopInterface $loop,
        protected RedisClientFactory $clientFactory,
        protected string $channel,
        protected string $name,
        protected array $server,
        protected $onConnect = null
    ) {
        //
    }

    /**
     * Create a new connetion to the Redis server.
     */
    public function connect(): void
    {
        $this->clientFactory->make($this->loop, $this->redisUrl())->then(
            function (Client $client) {
                $this->client = $client;
                $this->clientReconnectionTimer = 0;
                $this->configureClientErrorHandler();
                if ($this->onConnect) {
                    call_user_func($this->onConnect, $client);
                }

                Log::info("Redis connection to [{$this->name}] successful");
            },
            function (Exception $e) {
                $this->client = null;
                Log::error($e->getMessage());
                $this->reconnect();
            }
        );
    }

    /**
     * Attempt to reconnect to the Redis server.
     */
    public function reconnect(): void
    {
        if (! $this->shouldReconnect) {
            return;
        }

        $this->loop->addTimer(1, function () {
            $this->clientReconnectionTimer++;
            if ($this->clientReconnectionTimer >= $this->reconnectionTimeout()) {
                Log::error("Failed to reconnect to Redis connection [{$this->name}] within {$this->reconnectionTimeout()} second limit");

                exit;
            }
            Log::info("Attempting to reconnect Redis connection [{$this->name}]");
            $this->connect();
        });
    }

    /**
     * Disconnect from the Redis server.
     */
    public function disconnect(): void
    {
        $this->shouldReconnect = false;

        $this->client?->close();
    }

    /**
     * Subscribe to the given Redis channel.
     */
    public function subscribe(): void
    {
        if (! $this->isConnected($this->client)) {
            $this->queueSubscriptionEvent('subscribe', []);

            return;
        }

        $this->client->subscribe($this->channel);
    }

    /**
     * Publish an event to the given channel.
     */
    public function publish(array $payload): PromiseInterface
    {
        if (! $this->isConnected($this->client)) {
            $this->queuePublishEvent($payload);

            return new Promise(fn () => new RuntimeException);
        }

        return $this->client->publish($this->channel, json_encode($payload));
    }

    /**
     * Listen for a given event.
     */
    public function on(string $event, callable $callback): void
    {
        if (! $this->isConnected($this->client)) {
            $this->queueSubscriptionEvent('on', [$event => $callback]);

            return;
        }

        $this->client->on($event, $callback);
    }

    /**
     * Determine if the client is currently connected to the server.
     */
    public function isConnected(): bool
    {
        return (bool) $this->client === true && $this->client instanceof Client;
    }

    /**
     * Handle a connection failure to the Redis server.
     */
    protected function configureClientErrorHandler(): void
    {
        $this->client->on('close', function () {
            $this->client = null;
            Log::info("Disconnected fromRedis connection [{$this->name}]");
            $this->reconnect();
        });
    }

    /**
     * Queue the given subscription event.
     */
    protected function queueSubscriptionEvent($event, $payload): void
    {
        $this->queuedSubscriptionEvents[$event] = $payload;
    }

    /**
     * Queue the given publish event.
     */
    protected function queuePublishEvent(array $payload): void
    {
        $this->queuedPublishEvents[] = $payload;
    }

    /**
     * Process the queued subscription events.
     */
    protected function processQueuedSubscriptionEvents(): void
    {
        foreach ($this->queuedSubscriptionEvents as $event => $args) {
            match ($event) {
                'subscribe' => $this->subscribe(),
                'on' => $this->on(...$args),
                default => null
            };

        }
        $this->queuedSubscriptionEvents = [];
    }

    /**
     * Process the queued publish events.
     */
    protected function processQueuedPublishEvents(): void
    {
        foreach ($this->queuedPublishEvents as $event) {
            $this->publish($event);
        }
        $this->queuedPublishEvents = [];
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
     * Determine the configured reconnection timeout.
     */
    protected function reconnectionTimeout(): int
    {
        return (int) ($this->server['timeout'] ?? 60);
    }
}
