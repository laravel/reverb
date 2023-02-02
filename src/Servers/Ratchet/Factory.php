<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Http\Controllers\EventController;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class Factory
{
    /**
     * Create a new WebSocket server instance.
     */
    public static function make(string $host = '0.0.0.0', string $port = '8080', ?LoopInterface $loop = null): IoServer
    {
        $loop = $loop ?: Loop::get();

        $socket = new SocketServer("{$host}:{$port}", [], $loop);

        $app = new Router(
            new UrlMatcher(static::routes(), new RequestContext)
        );

        return new IoServer(
            new HttpServer($app),
            $socket,
            $loop
        );
    }

    /**
     * Generate the routes required to handle Pusher requests.
     */
    protected static function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('sockets', new Route('/app/{appId}', ['_controller' => static::handler()], [], [], null, [], ['GET']));
        $routes->add('events', new Route('/apps/{appId}/events', ['_controller' => EventController::class], [], [], null, [], ['POST']));

        return $routes;
    }

    /**
     * Build the WebSocket server.
     */
    protected static function handler(): WsServer
    {
        return new WsServer(
            App::make(Server::class)
        );
    }
}
