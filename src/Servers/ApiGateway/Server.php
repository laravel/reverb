<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Exception;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Server as ReverbServer;

class Server
{
    public function __construct(
        protected ReverbServer $server,
        protected ConnectionManager $connections
    ) {
    }

    /**
     * Handle the incoming API Gateway request.
     *
     * @param  \Laravel\Reverb\Servers\ApiGateway\Request  $request
     * @return void
     */
    public function handle(Request $request)
    {
        try {
            match ($request->event()) {
                'CONNECT' => $this->server->open(
                    $this->connection($request)
                ),
                'DISCONNECT' => $this->disconnect($request),
                'MESSAGE' => $this->server->message(
                    $this->connection($request),
                    $request->message()
                )
            };
        } catch (Exception $e) {
            $this->server->error(
                $this->connection($request),
                $e
            );
        }
    }

    /**
     * Get a Reverb connection from the API Gateway request.
     *
     * @param  \Laravel\Reverb\Servers\ApiGateway\Request  $request
     * @return \Laravel\Reverb\Servers\ApiGateway\Connection
     */
    protected function connection(Request $request): Connection
    {
        if ($application = $this->application($request)) {
            return $this->connections
                ->for($application)
                ->connect(
                    new Connection(
                        $request->connectionId(),
                        $application
                    )
                );
        }

        foreach (Application::all() as $application) {
            if ($connection = $this->connections->for($application)->find($request->connectionId())) {
                return $this->connections->connect($connection);
            }
        }

        throw new Exception('Unable to find connection for request.');
    }

    /**
     * Disconnect a connection.
     *
     * @param  string  $connectionId
     * @return void
     */
    protected function disconnect(Request $request)
    {
        $this->server->close(
            $this->connection($request)
        );
    }

    /**
     * Get the application instance for the request.
     *
     * @param  \Laravel\Reverb\Servers\ApiGateway\Request  $request
     * @return \Laravel\Reverb\Application|null
     */
    protected function application(Request $request): ?Application
    {
        try {
            parse_str($request->serverVariables['QUERY_STRING'], $queryString);

            return Application::findByKey($queryString['appId']);
        } catch (Exception $e) {
            return null;
        }
    }
}
