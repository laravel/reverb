<?php

namespace Reverb\Console\Commands;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Reverb\Contracts\ChannelManager;
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
        $loop = Loop::get();

        dd(Redis::getFacadeRoot());

        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe('websockets');
        $redis->on('message', function (string $channel, string $payload) {
            $event = json_decode($payload, true);
            foreach (app(ChannelManager::class)->all() as $connection) {
                if (isset($event['channels'])) {
                    foreach ($event['channels'] as $channel) {
                        $connection->send(json_encode([
                            'event' => $event['name'],
                            'channel' => $channel,
                            'data' => $event['data'],
                        ]));
                    }
                } else {
                    $connection->send(json_encode([
                        'event' => $event['name'],
                        'channel' => $event['channel'],
                        'data' => $event['data'],
                    ]));
                }
            }
        });

        $this->laravel->singleton(Client::class, function () use ($loop) {
            return (new Factory($loop))->createLazyClient(
                $this->redisUrl()
            );
        });

        $socket = new SocketServer('127.0.0.1:8080', [], $loop);

        $routes = new RouteCollection();
        $routes->add('sockets', new Route('/app/{appId}', ['_controller' => new WsServer(new Server(new \Reverb\Server()))], [], [], null, [], ['GET']));
        $routes->add('events', new Route('/apps/{appId}/events', ['_controller' => EventController::class], [], [], null, [], ['POST']));

        $app = new Router(
            new UrlMatcher($routes, new RequestContext())
        );

        $server = new IoServer(
            new HttpServer($app),
            $socket,
            $loop
        );

        echo 'Starting server on port 8080'.PHP_EOL;

        $server->run();
    }

    public function redisUrl()
    {
        $config = config('database.redis.default');

        $host = $config['host'];
        $port = $config['port'] ?: 6379;

        $query = [];

        if ($config['password']) {
            $query['password'] = $config['password'];
        }

        if ($config['database']) {
            $query['db'] = $config['database'];
        }

        $query = http_build_query($query);

        return "redis://{$host}:{$port}".($query ? "?{$query}" : '');
    }
}
