<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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
        match ($request->event()) {
            'CONNECT' => $this->server->open(
                $this->connection($request->connectionId())
            ),
            'DISCONNECT' => function () use ($request) {
                $this->server->close(
                    $this->connection($request->connectionId())
                );
                $this->mutex(fn () => $this->repository->forget("{$this->key()}:{$request->connectionId()}"));
            },
            'MESSAGE' => $this->server->message(
                $this->connection($request->connectionId()),
                $request->message()
            )
        };
    }

    /**
     * Get a Reverb connection from the API Gateway request.
     *
     * @param  string  $connectionId
     * @return \Laravel\Reverb\Servers\ApiGateway\Connection
     */
    protected function connection(string $connectionId): Connection
    {
        return $this->mutex(function () use ($connectionId) {
            return $this->repository->rememberForever(
                "{$this->key()}:{$connectionId}",
                fn () => new Connection($connectionId)
            );
        });
    }

    /**
     * Get the cache key for the connections.
     *
     * @return string
     */
    protected function key(): string
    {
        return "{$this->prefix}:connections";
    }
}
