<?php

namespace Laravel\Reverb\Http\Middleware;

use Closure;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Server;
use Laravel\Reverb\WebSockets\Connection;
use Laravel\Reverb\WebSockets\Request as WebSocketRequest;
use Laravel\Reverb\WebSockets\WsConnection;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class WebSocketMiddleware
{
    public function __construct(protected Server $server)
    {

    }

    /**
     * Invoke the WebSocket middleware.
     */
    public function __invoke(ServerRequestInterface $request, Closure $next): ServerRequestInterface|Response
    {
        $wsRequest = new WebSocketRequest($request);

        if (! $wsRequest->isWebSocketRequest()) {
            return $next($request);
        }

        $connection = $this->connection($request, $ws = $wsRequest->connect());

        $this->server->open($connection);

        $ws->on('message', fn (string $message) => $this->server->message($connection, $message));
        $ws->on('close', fn () => $this->server->close($connection));

        return $wsRequest->respond();
    }

    /**
     * Get the application from the request.
     */
    protected function application(ServerRequestInterface $request): Application
    {
        parse_str($request->getUri()->getQuery(), $queryString);

        return app(ApplicationProvider::class)->findByKey($queryString['appId']);
    }

    /**
     * Get the origin from the request.
     */
    protected function origin(ServerRequestInterface $request): ?string
    {
        return $request->getHeader('Origin')[0] ?? null;
    }

    /**
     * Get a Reverb connection from a Ratchet connection.
     */
    protected function connection(ServerRequestInterface $request, WsConnection $connection): Connection
    {
        return app(ConnectionManager::class)
            ->for($app = $this->application($request))
            ->resolve(
                $connection->resourceId,
                fn () => new Connection(
                    $connection,
                    $app,
                    $this->origin($request)
                )
            );
    }
}
