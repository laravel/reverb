<?php

namespace Laravel\Reverb\Http\Middleware;

use Closure;
use Laravel\Reverb\Server;
use Laravel\Reverb\WebSockets\Request as WebSocketRequest;
use Psr\Http\Message\ServerRequestInterface;

class WebSocketMiddleware
{
    public function __construct(protected Server $server)
    {

    }

    public function __invoke(ServerRequestInterface $request, Closure $next)
    {
        $wsRequest = new WebSocketRequest($request);

        if (! $wsRequest->isWebSocketRequest()) {
            return $next($request);
        }

        return $wsRequest->negotiate($request);
    }
}
