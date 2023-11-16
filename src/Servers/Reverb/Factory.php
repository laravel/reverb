<?php

namespace Laravel\Reverb\Servers\Reverb;

use Laravel\Reverb\Http\Route;
use Laravel\Reverb\Http\Router;
use Laravel\Reverb\Http\Server as HttpServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class Factory
{
    /**
     * Create a new WebSocket server instance.
     */
    public static function make(string $host = '0.0.0.0', string $port = '8080', LoopInterface $loop = null)
    {
        $loop = $loop ?: Loop::get();
        $socket = new SocketServer("{$host}:{$port}", [], $loop);
        $router = new Router(new UrlMatcher(static::routes(), new RequestContext));

        return new HttpServer($socket, $router, $loop);
    }

    /**
     * Generate the routes required to handle Pusher requests.
     */
    protected static function routes(): RouteCollection
    {
        $routes = new RouteCollection;

        $routes->add('sockets', Route::get('/app/{key}', new Controller));

        return $routes;
    }
}
