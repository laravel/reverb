<?php

use Laravel\Reverb\Servers\Reverb\Connection;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Servers\Reverb\Http\Server;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

require __DIR__.'/../../vendor/autoload.php';

$loop = Loop::get();
$socket = new SocketServer('0.0.0.0:8080', [], $loop);
$router = new Router(new UrlMatcher(routes(), new RequestContext));

$server = new Server($socket, $router, $loop);

echo "Server running at 0.0.0.0:8080\n";

$server->start();

function routes()
{
    $routes = new RouteCollection;
    $routes->add(
        'sockets',
        Route::get('/', function (RequestInterface $request, Connection $connection) {
            $connection->onMessage(function ($message) use ($connection) {
                $connection->send($message);
            });
            $connection->openBuffer();
        })
    );

    return $routes;
}
