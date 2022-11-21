<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Server as ReverbServer;

class Server
{
    use EnsuresIntegrity;

    protected $repository;

    protected $prefix;

    public function __construct(protected ReverbServer $server)
    {
        $config = Config::get('reverb.servers.api_gateway.connection_cache');

        $this->prefix = $config['prefix'];
        $this->repository = Cache::store($config['store']);
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
        return $this->mutex(function () use ($request) {
            $app = $this->application($request);

            return $this->repository->rememberForever(
                "{$this->key($app->id())}:{$request->connectionId()}",
                fn () => new Connection(
                    $request->connectionId(),
                    $app
                )
            );
        });
    }

    /**
     * Disconnect a connection.
     *
     * @param  string  $connectionId
     * @return void
     */
    protected function disconnect(Request $request)
    {
        $connection = $this->connection($request);

        $this->server->close($connection);

        $this->repository->forget(
            "{$this->key($connection->app()->id())}:{$request->connectionId()}"
        );
    }

    /**
     * Get the cache key for the connections.
     *
     * @return string
     */
    protected function key($appId): string
    {
        return "{$this->prefix}:{$appId}:connections";
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
