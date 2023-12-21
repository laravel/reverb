<?php

namespace Laravel\Reverb\Servers\Reverb\Http;

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

    /**
     * Create a new Http server instance.
     */
    public function __construct(protected ServerInterface $socket, protected Router $router, protected ?LoopInterface $loop = null)
    {
        gc_disable();

        $this->loop = $loop ?: Loop::get();

        $this->loop->addPeriodicTimer(30, fn () => gc_collect_cycles());

        $socket->on('connection', $this);
    }

    /**
     * Invoke the server.
     */
    public function __invoke(ConnectionInterface $connection): void
    {
        $connection = new Connection($connection);

        $connection->on('data', function ($data) use ($connection) {
            $this->handleRequest($data, $connection);
        });
    }

    /**
     * Start the Http server
     */
    public function start(): void
    {
        $this->loop->run();
    }

    /**
     * Stop the Http server
     */
    public function stop(): void
    {
        $this->loop->stop();
        $this->socket->close();
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
    protected function createRequest(string $message, Connection $connection): ?RequestInterface
    {
        try {
            $request = Request::from($message, $connection);
        } catch (OverflowException $e) {
            $this->close($connection, 413, 'Payload too large.');
        }

        return $request;
    }
}
