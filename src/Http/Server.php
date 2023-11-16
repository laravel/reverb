<?php

namespace Laravel\Reverb\Http;

use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

class Server
{
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
    protected function handleRequest(string $data, Connection $connection): void
    {
        if (! $connection->isInitialized()) {
            $request = Request::from($data);
            $connection->initialize();

            $this->router->dispatch($request, $connection);
        }
    }
}
