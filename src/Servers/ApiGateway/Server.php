<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Server as ReverbServer;
use Laravel\Reverb\Servers\ApiGateway\Jobs\SendToConnection;

class Server
{
    protected ConnectionManager $connections;

    public function __construct(
        protected ReverbServer $server,
        protected ApplicationProvider $applications,
    ) {
        $this->connections = app(ConnectionManager::class);
    }

    /**
     * Handle the incoming API Gateway request.
     */
    public function handle(Request $request): void
    {
        try {
            match ($request->event()) {
                'CONNECT' => $this->server->open(
                    $this->connect($request)
                ),
                'DISCONNECT' => $this->server->close(
                    $this->connect($request),
                ),
                'MESSAGE' => $this->server->message(
                    $this->connect($request),
                    $request->message()
                )
            };
        } catch (InvalidApplication $e) {
            SendToConnection::dispatch(
                $request->connectionId(),
                $e->message()
            );
        } catch (\Exception $e) {
            $this->server->error(
                $this->connect($request),
                $e
            );
        }
    }

    /**
     * Create a Reverb connection from the API Gateway request.
     */
    protected function connect(Request $request): Connection
    {
        $connection = $this->connections->find($request->connectionId());

        if ($connection) {
            return $connection;
        }

        $this->connections->connect(
            $connection = new Connection(
                $request->connectionId(),
                $this->application($request),
                $request->headers['origin'] ?? null
            )
        );

        return $connection;
    }

    /**
     * Get the application instance for the request.
     */
    protected function application(Request $request): ?Application
    {
        parse_str($request->serverVariables['QUERY_STRING'], $queryString);

        return $this->applications->findByKey($queryString['appId']);
    }
}
