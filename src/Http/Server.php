<?php

namespace Laravel\Reverb\Http;

use GuzzleHttp\Psr7\Message;
use Laravel\Reverb\Concerns\ClosesConnections;
use OverflowException;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

class Server
{
    use ClosesConnections;

    public function __construct(protected ServerInterface $socket, protected Router $router, protected ?LoopInterface $loop = null)
    {
        $this->loop = $loop ?: Loop::get();

        $socket->on('connection', $this);
    }

    public function __invoke(ConnectionInterface $connection)
    {
        $connection = new Connection($connection);

        $connection->on('data', function ($data) use ($connection) {
            $this->handleRequest($data, $connection);
        });
        // $connection->on('close', function () use ($connection) {
        //     $this->handleEnd($conn);
        // });
        // $conn->on('error', function (\Exception $e) use ($conn) {
        //     $this->handleError($e, $conn);
        // });
    }

    /**
     * Start the Http server
     */
    public function start(): void
    {
        $this->loop->run();
    }

    /**
     * Handle an incoming request.
     */
    protected function handleRequest(string $message, Connection $connection): void
    {
        if ($connection->isConnected()) {
            return;
        }

        if (($request = $this->createRequest($message, $connection)) === null) {
            return;
        }

        $connection->connect();

        $this->router->dispatch($request, $connection);
    }

    /**
     * Create a Psr7 request from the incoming message.
     */
    protected function createRequest(string $message, Connection $connection): RequestInterface
    {
        try {
            return Request::from($message, $connection);
        } catch (OverflowException $e) {
            $this->close($connection, 413, 'Payload too large.');
        }
    }
}
