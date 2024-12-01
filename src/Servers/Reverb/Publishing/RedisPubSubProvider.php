<?php

namespace Laravel\Reverb\Servers\Reverb\Publishing;

use Clue\React\Redis\Client;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\ConfigurationUrlParser;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Loggers\Log;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class RedisPubSubProvider implements PubSubProvider
{
    protected $publishingClient;

    protected $subscribingClient;

    protected $publishingClientReconnectionTimer;

    protected $subscribingClientReconnectionTimer;

    protected $publishingClientReconnectionAttempts = 0;

    protected $subscribingClientReconnectionAttempts = 0;

    protected $queuedSubscriptionEvents = [];

    protected $queuedPublishEvents = [];

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
        $this->connectSubcribingClient($loop);
        $this->connectPublishingClient($loop);
    }

    protected function connectSubcribingClient($loop)
    {
        $this->clientFactory->make($loop, $this->redisUrl())->then(
            function (Client $client) use ($loop) {
                $this->subscribingClient = $client;
                $this->subscribingClientReconnectionTimer = null;
                $this->configureSubscribingClientErrorHandler($this->subscribingClient, $loop);
                $this->processQueuedSubscriptionEvents();
                $this->subscribe();
                Log::info('Redis subscriber connected');
            },
            function (Exception $e) use ($loop) {
                $this->subscribingClient = null;
                Log::error($e->getMessage());
                $this->reconnectSubscribingClient($loop);
            }
        );
    }

    protected function configureSubscribingClientErrorHandler(Client $client, LoopInterface $loop)
    {
        $client->on('close', function () use ($loop) {
            $this->subscribingClient = null;
            Log::info('Redis subscriber disconnected');
            $this->reconnectSubscribingClient($loop);
        });
    }

    protected function reconnectSubscribingClient(LoopInterface $loop)
    {
        $this->subscribingClientReconnectionTimer = $loop->addTimer(1, function () use ($loop) {
            $this->subscribingClientReconnectionAttempts++;
            if ($this->reconnectionTimeout() <= $this->subscribingClientReconnectionAttempts) {
                Log::error('Taking too long bruh');
                exit;
            }
            Log::info('Attempting to reconnect Redis subscriber');
            $this->connectSubcribingClient($loop);
        });
    }

    protected function connectPublishingClient($loop)
    {
        $this->clientFactory->make($loop, $this->redisUrl())->then(
            function (Client $client) use ($loop) {
                $this->publishingClient = $client;
                $this->publishingClientReconnectionTimer = null;
                $this->configurePublishingClientErrorHandler($this->publishingClient, $loop);
                $this->processQueuedPublishEvents();
                Log::info('Redis publisher connected');
            },
            function (Exception $e) use ($loop) {
                $this->publishingClient = null;
                Log::error($e->getMessage());
                $this->reconnectPublishingClient($loop);
            }
        );
    }

    protected function configurePublishingClientErrorHandler(Client $client, LoopInterface $loop)
    {
        $client->on('close', function () use ($loop) {
            $this->publishingClient = null;
            Log::info('Redis publisher disconnected');
            $this->reconnectPublishingClient($loop);
        });
    }

    protected function reconnectPublishingClient(LoopInterface $loop)
    {
        $this->publishingClientReconnectionTimer = $loop->addTimer(1, function () use ($loop) {
            if ($this->reconnectionTimeout() <= $this->publishingClientReconnectionAttempts) {
                Log::error('Taking too long bruh');
                exit;
            }
            Log::info('Attempting to reconnect Redis publisher');
            $this->connectPublishingClient($loop);
        });
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
        if (! $this->clientIsReady($this->subscribingClient)) {
            $this->queueSubscriptionEvent('subscribe');

            return;
        }

        Log::info('Subscribing');
        $this->subscribingClient->subscribe($this->channel);

        $this->subscribingClient->on('message', function (string $channel, string $payload) {
            $this->messageHandler->handle($payload);
        });

        $this->subscribingClient->on('unsubscribe', function (string $channel) {
            if ($this->channel === $channel) {
                $this->subscribingClient->subscribe($channel);
            }
        });
    }

    /**
     * Listen for a given event.
     */
    public function on(string $event, callable $callback): void
    {
        dump($event, $callback);

        if (! $this->clientIsReady($this->subscribingClient)) {
            $this->queueSubscriptionEvent('on', [$event => $callback]);
        }

        dump($event, $callback);

        $this->subscribingClient->on('message', function (string $channel, string $payload) use ($event, $callback) {
            $payload = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);

            if (($payload['type'] ?? null) === $event) {
                $callback($payload);
            }
        });
    }

    /**
     * Publish a payload to the publishingClientReconnectionTimer.
     */
    public function publish(array $payload): PromiseInterface
    {
        Log::info('Sending');
        if (! $this->clientIsReady($this->publishingClient)) {
            $this->queuePublishEvent($payload);

            return new Promise(fn () => new Exception('It\'s broken'));
        }

        Log::info('Publishing');

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

    protected function queueSubscriptionEvent(): void
    {
        $this->queuedSubscriptionEvents['subscribe'] = true;
    }

    protected function queuePublishEvent(array $payload): void
    {
        $this->queuedPublishEvents[] = $payload;
    }

    protected function clientIsReady(mixed $client): bool
    {
        return (bool) $client === true && $client instanceof Client;
    }

    protected function processQueuedSubscriptionEvents(): void
    {
        dump($this->queuedSubscriptionEvents);
        foreach ($this->queuedSubscriptionEvents as $event => $args) {
            match ($event) {
                'subscribe' => $this->subscribe(),
                'on' => $this->on(...$args),
                default => null
            };

        }
        $this->queuedSubscriptionEvents = [];
    }

    protected function processQueuedPublishEvents(): void
    {
        dump($this->queuedPublishEvents);
        foreach ($this->queuedPublishEvents as $event) {
            $this->publish($event);
        }
        $this->queuedPublishEvents = [];
    }

    protected function reconnectionTimeout()
    {
        return $this->server['timeout'] ?? 60;
    }
}
