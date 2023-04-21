<?php

namespace Laravel\Reverb\Servers\Swoole;

use Illuminate\Support\Str;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Server as ReverbServer;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server as SwooleServer;

class Server
{
    public function __construct(
        protected ReverbServer $server,
        protected ConnectionManager $connections,
        protected ApplicationProvider $applications,
    ) {
    }

    /**
     * Handle the a client connection.
     */
    public function onOpen(SwooleServer $server, Request $connection): void
    {
        $this->server->open(
            $this->connect($server, $connection)
        );
    }

    /**
     * Handle a new message received by the connected client.
     */
    public function onMessage(Frame $message): void
    {
        $this->server->message(
            $this->getConnection($message->fd),
            $message->data
        );
    }

    /**
     * Handle a client disconnection.
     */
    public function onClose(string $identifier): void
    {
        $this->server->close(
            $this->getConnection($identifier)
        );
    }

    /**
     * Get a Reverb connection from a Ratchet connection.
     *
     * @return \Laravel\Reverb\Servers\Ratchet\Connection
     */
    protected function connect(SwooleServer $server, Request $request): Connection
    {
        $application = $this->application($request);

        return $this->connections
            ->for($application)
            ->resolve(
                $request->fd,
                fn () => new Connection(
                    $application,
                    $server,
                    $request->fd,
                    $request->header['Origin'] ?? null
                )
            );
    }

    /**
     * Get a Reverb connection from the API Gateway request.
     */
    protected function getConnection(string $identifier): Connection
    {
        foreach ($this->applications->all() as $application) {
            if ($connection = $this->connections->for($application)->find($identifier)) {
                return $this->connections->connect($connection);
            }
        }

        throw new InvalidApplication;
    }

    /**
     * Get the application instance for the request.
     */
    protected function application(Request $request): Application
    {
        return $this->applications->findByKey(Str::afterLast($request->server['request_uri'], '/'));
    }
}
