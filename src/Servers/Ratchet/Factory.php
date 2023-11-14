<?php

namespace Laravel\Reverb\Servers\Ratchet;

use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Event;
use Laravel\Reverb\Http\Controllers\EventController;
use Laravel\Reverb\Http\Controllers\StatsController;
use Laravel\Reverb\Http\Middleware\WebSocketMiddleware;
use Laravel\Reverb\Server;
use Psr\Http\Message\ServerRequestInterface;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Route;
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

        $server = new HttpServer(
            $loop,
            new WebSocketMiddleware(App::make(Server::class)),
            function (ServerRequestInterface $request) {
                $payload = json_decode($request->getBody()->getContents(), true);
                parse_str($request->getUri()->getQuery(), $queryString);

                $app = app(ApplicationProvider::class)->findById($queryString['appId']);

                Event::dispatch($app, [
                    'event' => $payload['name'],
                    'channel' => $payload['channel'],
                    'data' => $payload['data'],
                ]);

                return Response::json([]);
            }
        );

        $server->listen($socket);
        $loop->run();
    }

    /**
     * Generate the routes required to handle Pusher requests.
     */
    protected static function routes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('sockets', new Route('/app/{appId}', ['_controller' => static::handler()], [], [], null, [], ['GET']));
        $routes->add('events', new Route('/apps/{appId}/events', ['_controller' => EventController::class], [], [], null, [], ['POST']));
        $routes->add('stats', new Route('/stats', ['_controller' => StatsController::class], [], [], null, [], ['GET']));

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
