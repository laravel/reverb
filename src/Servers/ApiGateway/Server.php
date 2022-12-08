<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Exception;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Exceptions\InvalidApplication;
use Laravel\Reverb\Exceptions\PusherException;
use Laravel\Reverb\Server as ReverbServer;
use Laravel\Reverb\Servers\ApiGateway\Jobs\SendToConnection;

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
        } catch (PusherException $e) {
            SendToConnection::dispatch($request->connectionId(), json_encode($e->payload()));
        } catch (\Exception $e) {
            SendToConnection::dispatch(
                $request->connectionId(),
                json_encode([
                    'event' => 'pusher:error',
                    'data' => json_encode([
                        'code' => 4200,
                        'message' => $e->getMessage(),
                    ]),
                ])
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
            if ($connection = $this->connections->for($application)->find($request->connectionId())) {
                return $connection;
            }

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

        throw new InvalidApplication;
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
