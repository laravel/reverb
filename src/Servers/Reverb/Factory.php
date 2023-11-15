<?php

namespace Laravel\Reverb\Servers\Reverb;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Event;
use Laravel\Reverb\Http\Controllers\EventController;
use Laravel\Reverb\Http\Controllers\StatsController;
use Laravel\Reverb\HttpServer as ReverbHttpServer;
use Laravel\Reverb\Server;
use Laravel\Reverb\WebSockets\WebSocketMiddleware;
use Psr\Http\Message\ServerRequestInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Http\HttpServer;
use React\Http\Message\Response;
use React\Http\Middleware\LimitConcurrentRequestsMiddleware;
use React\Http\Middleware\StreamingRequestMiddleware;
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

        return new ReverbHttpServer($socket, $loop);

        // dump('Starting server...');
        // $server = new HttpServer(
        //     $loop,
        //     new LimitConcurrentRequestsMiddleware(10000),
        //     new StreamingRequestMiddleware(),
        //     new WebSocketMiddleware(App::make(Server::class)),
        //     function (ServerRequestInterface $request) {
        //         $payload = json_decode($request->getBody()->getContents(), true);
        //         $appId = Str::beforeLast($request->getUri()->getPath(), '/');
        //         $appId = Str::afterLast($appId, '/');

        //         $app = app(ApplicationProvider::class)->findById($appId);

        //         Event::dispatch($app, [
        //             'event' => $payload['name'],
        //             'channel' => $payload['channel'],
        //             'data' => $payload['data'],
        //         ]);

        //         return Response::json(['status' => 'success']);
        //     }
        // );
    }

    /**
     * Generate the routes required to handle Pusher requests.
     */
    // protected static function routes(): RouteCollection
    // {
    //     $routes = new RouteCollection();
    //     $routes->add('sockets', new Route('/app/{appId}', ['_controller' => static::handler()], [], [], null, [], ['GET']));
    //     $routes->add('events', new Route('/apps/{appId}/events', ['_controller' => EventController::class], [], [], null, [], ['POST']));
    //     $routes->add('stats', new Route('/stats', ['_controller' => StatsController::class], [], [], null, [], ['GET']));

    //     return $routes;
    // }
}
