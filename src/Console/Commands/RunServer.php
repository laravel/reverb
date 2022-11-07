<?php

namespace Reverb\Console\Commands;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Reverb\Channels\Channel;
use Reverb\Channels\ChannelBroker;
use Reverb\Contracts\ChannelManager;
use Reverb\Contracts\ConnectionManager;
use Reverb\Http\Controllers\EventController;
use Reverb\Http\Controllers\StatsController;
use Reverb\Ratchet\Server;
use Reverb\Server as ReverbServer;
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

        $this->bindRedis($loop);
        $this->subscribe($loop);

        $socket = new SocketServer('127.0.0.1:8080', [], $loop);

        $app = new Router(
            new UrlMatcher($this->generateRoutes(), new RequestContext())
        );

        $server = new IoServer(
            new HttpServer($app),
            $socket,
            $loop
        );

        echo 'Starting server on port 8080'.PHP_EOL;

        $server->run();
    }

    /**
     * Get the connection URL for Redis.
     *
     * @return string
     */
    protected function redisUrl(): string
    {
        $config = $this->laravel->config['database.redis.default'];

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

    /**
     * Bind the Redis client to the container.
     *
     * @param  \React\EventLoop\LoopInterface  $loop
     * @return void
     */
    protected function bindRedis(LoopInterface $loop): void
    {
        $this->laravel->singleton(Client::class, function () use ($loop) {
            return (new Factory($loop))->createLazyClient(
                $this->redisUrl()
            );
        });
    }

    /**
     * Subscribe to the Redis pub/sub channel.
     *
     * @param  \React\EventLoop\LoopInterface  $loop
     * @return void
     */
    protected function subscribe(LoopInterface $loop): void
    {
        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe('websockets');

        $redis->on('message', function (string $channel, string $payload) {
            $event = json_decode($payload, true);
            $channels = isset($event['channel']) ? [$event['channel']] : $event['channels'];

            foreach ($channels as $channel) {
                $channel = ChannelBroker::create($channel);

                $this->laravel->make(ChannelManager::class)
                    ->broadcast($channel, [
                        'event' => $event['name'],
                        'channel' => $channel->name(),
                        'data' => $event['data'],
                    ]);
            }
        });
    }

    /**
     * Generate the routes required to handle Pusher requests.
     *
     * @return \Symfony\Component\Routing\RouteCollection
     */
    protected function generateRoutes(): RouteCollection
    {
        $routes = new RouteCollection();
        $routes->add('sockets', new Route('/app/{appId}', ['_controller' => $this->buildWebSocketServer()], [], [], null, [], ['GET']));
        $routes->add('events', new Route('/apps/{appId}/events', ['_controller' => EventController::class], [], [], null, [], ['POST']));
        $routes->add('stats', new Route('/stats', ['_controller' => StatsController::class], [], [], null, [], ['GET']));

        return $routes;
    }

    /**
     * Build the WebSocket server.
     *
     * @return \Ratchet\WebSocket\WsServer
     */
    protected function buildWebSocketServer()
    {
        return new WsServer(
            new Server(
                $this->laravel->make(ReverbServer::class),
                $this->laravel->make(ConnectionManager::class)
            )
        );
    }
}
