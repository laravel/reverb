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
    public function __construct(
        protected ReverbServer $server,
        protected ConnectionManager $connections,
        protected ApplicationProvider $applications,
    ) {
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
                    $this->getConnection($request)
                ),
                'MESSAGE' => $this->server->message(
                    $this->getConnection($request),
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
                $this->getConnection($request),
                $e
            );
        }
    }

    /**
     * Create a Reverb connection from the API Gateway request.
     */
    protected function connect(Request $request): Connection
    {
        return $this->connections
                ->for($application = $this->application($request))
                ->resolve(
                    $request->connectionId(),
                    fn () => new Connection(
                        $request->connectionId(),
                        $application,
                        $request->headers['origin'] ?? null
                    )
                );
    }

    /**
     * Get a Reverb connection from the API Gateway request.
     */
    protected function getConnection(Request $request): Connection
    {
        foreach ($this->applications->all() as $application) {
            if ($connection = $this->connections->for($application)->find($request->connectionId())) {
                return $this->connections->connect($connection);
            }
        }

        throw new InvalidApplication;
    }

    /**
     * Get the application instance for the request.
     */
    protected function application(Request $request): Application|null
    {
        parse_str($request->serverVariables['QUERY_STRING'], $queryString);

        return $this->applications->findByKey($queryString['appId']);
    }
}
