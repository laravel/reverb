<?php

namespace Laravel\Reverb\Servers\Reverb\Publishing;

use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

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
        $this->subscribingClient = new RedisClient(
            $loop,
            $this->clientFactory,
            $this->channel,
            'subscriber',
            $this->server,
            fn () => $this->subscribe());
        $this->subscribingClient->connect();

        $this->publishingClient = new RedisClient(
            $loop,
            $this->clientFactory,
            $this->channel,
            'publisher',
            $this->server
        );
        $this->publishingClient->connect();
    }

    /**
     * Disconnect from the publisher.
     */
    public function disconnect(): void
    {
        $this->subscribingClient?->disconnect();
        $this->publishingClient?->disconnect();
    }

    /**
     * Subscribe to the publisher.
     */
    public function subscribe(): void
    {
        $this->subscribingClient->subscribe();

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
        return $this->publishingClient->publish($payload);
    }
}
