<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Exception;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationsProvider;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Server as ReverbServer;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;

class Server implements MessageComponentInterface
{
    public function __construct(
        protected ReverbServer $server,
        protected ConnectionManager $connections,
        protected ApplicationsProvider $applications,
    ) {
    }

    /**
     * Handle the a client connection.
     */
    public function onOpen(ConnectionInterface $connection): void
    {
        $this->server->open(
            $this->connection($connection)
        );
    }

    /**
     * Handle a new message received by the connected client.
     *
     * @param  string  $message
     */
    public function onMessage(ConnectionInterface $from, $message): void
    {
        $this->server->message(
            $this->connection($from),
            $message
        );
    }

    /**
     * Handle a client disconnection.
     */
    public function onClose(ConnectionInterface $connection): void
    {
        $this->server->close(
            $this->connection($connection)
        );
    }

    /**
     * Handle an error.
     */
    public function onError(ConnectionInterface $connection, Exception $e): void
    {
        if ($e instanceof InvalidApplication) {
            $connection->send(
                $e->message()
            );

            return;
        }

        $this->server->error(
            $this->connection($connection),
            $e
        );
    }

    /**
     * Get a Reverb connection from a Ratchet connection.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return \Laravel\Reverb\Servers\Ratchet\Connection
     */
    protected function connection(ConnectionInterface $connection): Connection
    {
        $application = $this->application($connection);

        return $this->connections
                ->for($application)
                ->resolve(
                    $connection->resourceId,
                    fn () => new Connection(
                        $connection,
                        $application,
                        $connection->httpRequest->getHeader('Origin')[0] ?? null
                    )
                );
    }

    /**
     * Get the application instance for the request.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return \Laravel\Reverb\Application
     */
    protected function application(ConnectionInterface $connection): Application
    {
        $request = $connection->httpRequest;
        parse_str($request->getUri()->getQuery(), $queryString);

        return $this->applications->findByKey($queryString['appId']);
    }
}
