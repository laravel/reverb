<?php

namespace Laravel\Reverb\Servers\Reverb\Publishing;

use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

class RedisPubSubProvider implements PubSubProvider
{
    /**
     * The Redis publisher client.
     *
     * @var \Laravel\Reverb\Servers\Reverb\Publishing\RedisPublishClient
     */
    protected $publisher;

    /**
     * The Redis subscriber client.
     *
     * @var \Laravel\Reverb\Servers\Reverb\Publishing\RedisSubscribeClient
     */
    protected $subscriber;

    /**
     * Instantiate a new instance of the provider.
     */
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
        $properties = [$loop, $this->clientFactory, $this->channel, $this->server];

        $this->publisher = new RedisPublishClient(...$properties);
        $this->subscriber = new RedisSubscribeClient(...array_merge($properties, [fn () => $this->subscribe()]));

        $this->publisher->connect();
        $this->subscriber->connect();
    }

    /**
     * Disconnect from the publisher.
     */
    public function disconnect(): void
    {
        $this->subscriber?->disconnect();
        $this->publisher?->disconnect();
    }

    /**
     * Subscribe to the publisher.
     */
    public function subscribe(): void
    {
        $this->subscriber->subscribe();

        $this->subscriber->on('message', function (string $channel, string $payload) {
            $this->messageHandler->handle($payload);
        });

        $this->subscriber->on('unsubscribe', function (string $channel) {
            if ($this->channel === $channel) {
                $this->subscriber->subscribe($channel);
            }
        });
    }

    /**
     * Listen for a given event.
     */
    public function on(string $event, callable $callback): void
    {
        $this->subscriber->on('message', function (string $channel, string $payload) use ($event, $callback) {
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
        return $this->publisher->publish($payload);
    }
}
