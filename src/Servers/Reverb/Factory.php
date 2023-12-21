<?php

namespace Laravel\Reverb\Servers\Reverb;

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Servers\Reverb\Http\Route;
use Laravel\Reverb\Servers\Reverb\Http\Router;
use Laravel\Reverb\Servers\Reverb\Http\Server as HttpServer;
use Laravel\Reverb\Pusher\Http\Controllers\ChannelController;
use Laravel\Reverb\Pusher\Http\Controllers\ChannelsController;
use Laravel\Reverb\Pusher\Http\Controllers\ChannelUsersController;
use Laravel\Reverb\Pusher\Http\Controllers\EventsBatchController;
use Laravel\Reverb\Pusher\Http\Controllers\EventsController;
use Laravel\Reverb\Pusher\Http\Controllers\UsersTerminateController;
use Laravel\Reverb\Pusher\Server as PusherServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Laravel\Reverb\Pusher\Http\Controllers\PusherController;

class Factory
{
    /**
     * Create a new WebSocket server instance.
     */
    public static function make(string $host = '0.0.0.0', string $port = '8080', ?LoopInterface $loop = null): HttpServer
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

        $routes->add('sockets', Route::get('/app/{appKey}', new PusherController(app(PusherServer::class), app(ApplicationProvider::class))));
        $routes->add('events', Route::post('/apps/{appId}/events', new EventsController));
        $routes->add('events_batch', Route::post('/apps/{appId}/batch_events', new EventsBatchController));
        $routes->add('channels', Route::get('/apps/{appId}/channels', new ChannelsController));
        $routes->add('channel', Route::get('/apps/{appId}/channels/{channel}', new ChannelController));
        $routes->add('channel_users', Route::get('/apps/{appId}/channels/{channel}/users', new ChannelUsersController));
        $routes->add('users_terminate', Route::post('/apps/{appId}/users/{userId}/terminate_connections', new UsersTerminateController));

        return $routes;
    }
}
