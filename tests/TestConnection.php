<?php

namespace Laravel\Reverb\Tests;

use Ratchet\Client\WebSocket;
use React\Promise\Deferred;
use React\Promise\Timer\TimeoutException;

use function React\Async\await;
use function React\Promise\Timer\timeout;

class TestConnection
{
    /**
     * The socket ID.
     *
     * @var string
     */
    public $socketId;

    /**
     * Messages received by the connection.
     *
     * @var array<int, string>
     */
    public $receivedMessages = [];

    /**
     * Create a new test connection instance.
     */
    public function __construct(public WebSocket $connection)
    {
        $connection->on('message', function ($message) {
            $this->receivedMessages[] = (string) $message;
        });

        $connection->on('close', function ($code, $message) {
            $this->receivedMessages[] = (string) $message;
        });
    }

    /**
     * Get the socket ID of the connection.
     */
    public function socketId(): string
    {
        return $this->socketId;
    }

    /**
     * Await all messages to the connection to be resolved.
     */
    public function await(): mixed
    {
        $promise = new Deferred;

        $this->connection->on('message', function ($message) use ($promise) {
            $promise->resolve((string) $message);
        });

        $this->connection->on('close', function ($code, $message) use ($promise) {
            $promise->resolve((string) $message);
        });

        return await(
            timeout($promise->promise(), 2)
                ->then(
                    fn ($message) => $message,
                    fn (TimeoutException $error) => false
                )
        );
    }

    /**
     * Assert that the connection received the given message.
     */
    public function assertReceived(string $message, ?int $count = null): void
    {
        if (! in_array($message, $this->receivedMessages) || $count !== null) {
            $this->await();
        }

        if ($count) {
            $matches = array_filter($this->receivedMessages, fn ($m) => $m === $message);

            expect($matches)->toHaveCount($count);
        }

        expect($this->receivedMessages)->toContain($message);
    }

    /**
     * Proxy method calls to the connection.
     */
    public function __call($method, $arguments)
    {
        return $this->connection->{$method}(...$arguments);
    }
}
