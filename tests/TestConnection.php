<?php

namespace Laravel\Reverb\Tests;

use Ratchet\Client\WebSocket;
use React\Promise\Deferred;
use React\Promise\Timer\TimeoutException;

use function React\Async\await;
use function React\Promise\Timer\timeout;

class TestConnection
{
    public $socketId;

    public $receivedMessages = [];

    public function __construct(public WebSocket $connection)
    {
        $connection->on('message', function ($message) {
            $this->receivedMessages[] = (string) $message;
        });

        $connection->on('close', function ($code, $message) {
            $this->receivedMessages[] = (string) $message;
        });
    }

    public function socketId(): string
    {
        return $this->socketId;
    }

    public function __call($method, $arguments)
    {
        return $this->connection->{$method}(...$arguments);
    }

    public function await()
    {
        $promise = new Deferred();

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

    public function assertReceived(string $message, ?int $count = null)
    {
        if (! in_array($message, $this->receivedMessages) || $count !== null) {
            $this->await();
        }

        if ($count) {
            $matches = array_filter($this->receivedMessages, fn ($m) => $m === $message);

            expect($matches)->toHaveCount($count);
        }

        return expect($this->receivedMessages)->toContain($message);
    }
}
