<?php

namespace Reverb\Console\Commands;

use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Reverb\Http\Controllers\EventController;
use Reverb\Ratchet\Server;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class RunServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Reverb server';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $routes = new RouteCollection();
        $routes->add('sockets', new Route('/app/{appId}', ['_controller' => new WsServer(new Server(new \Reverb\Server()))], [], [], null, [], ['GET']));
        $routes->add('events', new Route('/apps/{appId}/events', ['_controller' => EventController::class], [], [], null, [], ['POST']));

        $app = new Router(
            new UrlMatcher($routes, new RequestContext())
        );

        $server = IoServer::factory(
            new HttpServer(
                $app
            ),
            8080
        );

        $server->run();
    }
}
