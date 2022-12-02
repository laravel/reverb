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
     * @param  string  $connectionId
     * @return \Laravel\Reverb\Servers\ApiGateway\Connection
     */
    protected function connection(Request $request): Connection
    {
        $application = $this->application($request);

        return $this->connections
            ->for($application)
            ->resolve(
                $request->connectionId(),
                function () use ($request, $application) {
                    return new Connection(
                        $request->connectionId(),
                        $application
                    );
                }
            );
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
     * @return \Laravel\Reverb\Application
     */
    protected function application(Request $request): Application
    {
        parse_str($request->serverVariables['QUERY_STRING'], $queryString);

        return Application::findByKey($queryString['appId']);
    }
}
