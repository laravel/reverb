<?php

namespace Laravel\Reverb\Tests;

use Clue\React\Redis\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Event;
use Laravel\Reverb\Loggers\NullLogger;
use Laravel\Reverb\Servers\Reverb\Factory;
use Ratchet\Client\WebSocket;
use React\Async\SimpleFiber;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use React\Promise\Timer\TimeoutException;
use ReflectionObject;

use function Ratchet\Client\connect;
use function React\Async\await;
use function React\Promise\Timer\timeout;

class RatchetTestCase extends TestCase
{
    use InteractsWithAsyncRedis;

    protected $server;

    protected $loop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Logger::class, new NullLogger);
        $this->loop = Loop::get();
        $this->startServer();
    }

    protected function tearDown(): void
    {
        $this->stopServer();

        parent::tearDown();
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        parent::defineEnvironment($app);

        $app['config']->set('reverb.apps.apps.1', [
            'id' => '654321',
            'key' => 'pusher-key-2',
            'secret' => 'pusher-secret-2',
            'capacity' => null,
            'allowed_origins' => ['*'],
            'ping_interval' => 10,
        ]);

        $app['config']->set('reverb.apps.apps.2', [
            'id' => '987654',
            'key' => 'pusher-key-3',
            'secret' => 'pusher-secret-3',
            'capacity' => null,
            'allowed_origins' => ['laravel.com'],
            'ping_interval' => 10,
        ]);
    }

    public function usingRedis()
    {
        app(ServerProvider::class)->withPublishing();

        // $this->app['config']->set('reverb.servers.ratchet.publish_events.enabled', true);

        $this->bindRedis($this->loop);
        $this->subscribeToRedis($this->loop);
    }

    /**
     * Start the WebSocket server.
     *
     * @param  string  $host
     * @param  string  $port
     * @return void
     */
    public function startServer($host = '0.0.0.0', $port = '8080')
    {
        $this->resetFiber();
        $this->server = Factory::make($host, $port, $this->loop);
    }

    /**
     * Reset the Fiber instance.
     * This prevents using a stale fiber between tests.
     *
     * @return void
     */
    protected function resetFiber()
    {
        $fiber = new SimpleFiber();
        $fiberRef = new ReflectionObject($fiber);
        $scheduler = $fiberRef->getProperty('scheduler');
        $scheduler->setAccessible(true);
        $scheduler->setValue(null, null);
    }

    /**
     * Stop the running WebSocket server.
     *
     * @return void
     */
    public function stopServer()
    {
        if ($this->server) {
            $this->server->stop();
        }
    }

    /**
     * Connect to the WebSocket server.
     *
     * @param  string  $host
     * @param  string  $port
     * @param  string  $key
     * @return \Ratchet\Client\WebSocket
     */
    public function connect($host = '0.0.0.0', $port = '8080', $key = 'pusher-key', $headers = [])
    {
        $promise = new Deferred;

        $connection = await(
            connect("ws://{$host}:{$port}/app/{$key}", headers: $headers)
        );

        $connection->on('message', function ($message) use ($promise) {
            $promise->resolve((string) $message);
        });

        $this->assertTrue(
            Str::contains(
                await($promise->promise()),
                'connection_established'
            )
        );

        return $connection;
    }

    /**
     * Send a message to the connected client.
     */
    public function send(array $message, WebSocket $connection = null): string
    {
        $promise = new Deferred;

        $connection = $connection ?: $this->connect();

        $connection->on('message', function ($message) use ($promise) {
            $promise->resolve((string) $message);
        });

        $connection->send(json_encode($message));

        return await($promise->promise());
    }

    /**
     * Disconnect the connected client.
     */
    public function disconnect(WebSocket $connection): string
    {
        $promise = new Deferred;

        $connection->on('close', function () use ($promise) {
            $promise->resolve('Connection Closed.');
        });

        $connection->close();

        return await($promise->promise());
    }

    /**
     * Subscribe to a channel.
     */
    public function subscribe(string $channel, ?array $data = [], string $auth = null, WebSocket $connection = null): string
    {
        $data = ! empty($data) ? json_encode($data) : null;

        if (! $auth && Str::startsWith($channel, ['private-', 'presence-'])) {
            $connection = $connection ?: $this->connect();
            $managed = $this->managedConnection($connection);
            $auth = validAuth($managed, $channel, $data);
        }

        return $this->send([
            'event' => 'pusher:subscribe',
            'data' => array_filter([
                'channel' => $channel,
                'channel_data' => $data,
                'auth' => $auth,
            ]),
        ], $connection);
    }

    /**
     * Return the latest connection set on the manager.
     */
    public function managedConnection(): ?Connection
    {
        return Arr::last(connectionManager()->all());
    }

    /**
     * Return a promise for the next message received to the given connection.
     *
     * @param  \Ratchet\Client\WebSocketWebSocket  $connection
     */
    public function messagePromise(WebSocket $connection)
    {
        $promise = new Deferred;

        $connection->on('message', function ($message) use ($promise) {
            $promise->resolve((string) $message);
        });

        return timeout($promise->promise(), 0.1, $this->loop)
            ->then(
                fn ($message) => $message,
                fn (TimeoutException $error) => false
            );
    }

    /**
     * Return a promise when a given connection is disconnected.
     *
     * @param  \Ratchet\Client\WebSocketWebSocket  $connection
     */
    public function disconnectPromise(WebSocket $connection): PromiseInterface
    {
        $promise = new Deferred;

        $connection->on('close', function ($message) use ($promise) {
            $promise->resolve('Connection Closed.');
        });

        return $promise->promise();
    }

    /**
     * Send an event to the server.
     */
    public function triggerEvent(string $channel, string $event, array $data = []): void
    {
        $response = await($this->postToServer('events', [
            'name' => $event,
            'channel' => $channel,
            'data' => $data,
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{}', $response->getBody()->getContents());
    }

    public function request(string $path, string $method = 'GET', mixed $data = '', string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        return (new Browser($this->loop))
            ->request(
                $method,
                "http://{$host}:{$port}/apps/{$appId}/{$path}",
                [],
                ($data) ? json_encode($data) : ''
            );
    }

    /**
     * Post a request to the server.
     */
    public function postToServer(
        string $path,
        array $data = [],
        string $host = '0.0.0.0',
        string $port = '8080',
        string $appId = '123456'
    ): PromiseInterface {
        return $this->request($path, 'POST', $data, $host, $port, $appId);
    }

    /**
     * Post a signed request to the server.
     */
    public function postToServerWithSignature(
        string $path,
        array $data = [],
        string $host = '0.0.0.0',
        string $port = '8080',
        string $appId = '123456'
    ): PromiseInterface {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=pusher-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'pusher-secret');

        return $this->postToServer("{$path}?{$query}&auth_signature={$signature}", $data, $host, $port, $appId);
    }

    public function getWithSignature(
        string $path,
        array $data = [],
        string $host = '0.0.0.0',
        string $port = '8080',
        string $appId = '123456'
    ): PromiseInterface {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=pusher-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'pusher-secret');

        $path = Str::contains($path, '?') ? "{$path}&{$query}" : "{$path}?{$query}";
        
        return $this->request("{$path}&auth_signature={$signature}", 'GET', '', $host, $port, $appId);
    }
}
