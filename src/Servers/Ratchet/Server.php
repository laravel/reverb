<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\PusherException;
use Laravel\Reverb\Server as ReverbServer;
use Ratchet\ConnectionInterface;
use Ratchet\WebSocket\MessageComponentInterface;

class Server implements MessageComponentInterface
{
    public function __construct(
        protected ReverbServer $server,
        protected ConnectionManager $connections
    ) {
    }

    /**
     * Handle the a client connection.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return void
     */
    public function onOpen(ConnectionInterface $connection)
    {
        if (! $connection = $this->connection($connection)) {
            return;
        }

        $this->server->open($connection);
    }

    /**
     * Handle a new message received by the connected client.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @param  string  $message
     * @return void
     */
    public function onMessage(ConnectionInterface $from, $message)
    {
        if (! $connection = $this->connection($from)) {
            return;
        }

        $this->server->message(
            $connection,
            $message
        );
    }

    /**
     * Handle a client disconnection.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return void
     */
    public function onClose(ConnectionInterface $connection)
    {
        if (! $connection = $this->connection($connection)) {
            return;
        }

        $this->server->close($connection);
    }

    /**
     * Handle an error.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @param  \Exception  $e
     * @return void
     */
    public function onError(ConnectionInterface $connection, \Exception $e)
    {
        if (! $connection = $this->connection($connection)) {
            return;
        }

        $this->server->error(
            $connection,
            $e
        );
    }

    /**
     * Get a Reverb connection from a Ratchet connection.
     *
     * @param  \Ratchet\ConnectionInterface  $connection
     * @return \Laravel\Reverb\Servers\Ratchet\Connection|null
     */
    protected function connection(ConnectionInterface $connection): ?Connection
    {
        try {
            $application = $this->application($connection);

            return $this->connections
                ->for($application)
                ->resolve(
                    $connection->resourceId,
                    function () use ($connection, $application) {
                        return new Connection(
                            $connection,
                            $application
                        );
                    }
                );
        } catch (PusherException $e) {
            $connection->send(json_encode($e->payload()));
            $connection->close();
        } catch (\Exception $e) {
            $connection->send(json_encode([
                'event' => 'pusher:error',
                'data' => json_encode([
                    'code' => 4200,
                    'message' => $e->getMessage(),
                ]),
            ]));
            $connection->close();
        }

        return null;
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

        return Application::findByKey($queryString['appId']);
    }
}
