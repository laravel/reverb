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
     * Map of callback object IDs to their wrapper functions.
     *
     * @var array<string, callable>
     */
    protected $listenerMap = [];

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
    }

    /**
     * Listen for a given event.
     */
    public function on(string $event, callable $callback): void
    {
        $wrapper = function (string $channel, string $payload) use ($event, $callback) {
            $payload = json_decode($payload, associative: true, flags: JSON_THROW_ON_ERROR);

            if (($payload['type'] ?? null) === $event) {
                $callback($payload);
            }
        };

        $key = $event.':'.spl_object_id($callback);
        $this->listenerMap[$key] = $wrapper;

        $this->subscriber->on('message', $wrapper);
    }

    /**
     * Remove a listener for a given event.
     */
    public function off(string $event, callable $callback): void
    {
        $key = $event.':'.spl_object_id($callback);

        if (! isset($this->listenerMap[$key])) {
            return;
        }

        $wrapper = $this->listenerMap[$key];
        unset($this->listenerMap[$key]);

        $this->subscriber->removeListener('message', $wrapper);
    }

    /**
     * Publish a payload to the publishingClientReconnectionTimer.
     */
    public function publish(array $payload): PromiseInterface
    {
        return $this->publisher->publish($payload);
    }
}
