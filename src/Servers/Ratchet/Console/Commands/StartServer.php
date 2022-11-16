<?php

namespace Laravel\Reverb\Servers\Ratchet\Console\Commands;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Exception;
use Illuminate\Console\Command;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Event;
use Laravel\Reverb\Http\Controllers\EventController;
use Laravel\Reverb\Http\Controllers\StatsController;
use Laravel\Reverb\Server as ReverbServer;
use Laravel\Reverb\Servers\Ratchet\Server;
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

class StartServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ratchet:start
                {--host=}
                {--port=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Ratchet Reverb server';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $config = $this->laravel['config']['reverb.servers.ratchet'];
        $host = $this->option('host') ?: $config['host'];
        $port = $this->option('port') ?: $config['port'];

        $loop = Loop::get();

        $this->bindRedis($loop);
        $this->subscribe($loop);

        $socket = new SocketServer("{$host}:{$port}", [], $loop);

        $app = new Router(
            new UrlMatcher($this->generateRoutes(), new RequestContext())
        );

        $server = new IoServer(
            new HttpServer($app),
            $socket,
            $loop
        );

        $this->components->info("Starting server on {$host}:{$port}");

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
     * Subscribe to the Redis pub / sub channel.
     *
     * @param  \React\EventLoop\LoopInterface  $loop
     * @return void
     */
    protected function subscribe(LoopInterface $loop): void
    {
        $config = $this->laravel['config']['reverb']['pubsub'];

        if (! $config['enabled']) {
            return;
        }

        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe($config['channel']);

        $redis->on('error', function (Exception $e) {
            echo 'Error: '.$e->getMessage().PHP_EOL;
        });

        $redis->on('message', function (string $channel, string $payload) {
            Event::dispatchSynchronously($payload);
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
