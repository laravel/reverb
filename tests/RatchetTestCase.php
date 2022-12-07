<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Support\Str;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\NullLogger;
use Laravel\Reverb\Servers\Ratchet\Factory;
use function Ratchet\Client\connect;
use Ratchet\Client\WebSocket;
use function React\Async\await;
use React\Async\SimpleFiber;
use React\EventLoop\Factory as LoopFactory;
use React\Promise\Deferred;
use React\Promise\PromiseInterface;
use ReflectionObject;

class RatchetTestCase extends TestCase
{
    protected $server;

    protected $loop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Logger::class, new NullLogger);
        $this->loop = LoopFactory::create();
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

        $app['config']->set('reverb.apps.1', [
            'id' => '654321',
            'key' => 'pusher-key-2',
            'secret' => 'pusher-secret-2',
            'capacity' => null,
            'allowed_origins' => [
                //
            ],
            'ping_interval' => 10,
        ]);
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
            $this->loop->stop();
            $this->server->socket->close();
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
    public function connect($host = '0.0.0.0', $port = '8080', $key = 'pusher-key')
    {
        $promise = new Deferred;

        $connection = await(
            connect("ws://{$host}:{$port}/app/{$key}")
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
     *
     * @param  array  $message
     * @param  \Ratchet\Client\WebSocket|null  $connection
     * @return string
     */
    public function send(array $message, ?WebSocket $connection = null): string
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
     *
     * @param  \Ratchet\Client\WebSocket  $connection
     * @return string
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
     *
     * @param  string  $channel
     * @param  array  $data
     * @param  string  $auth
     * @param  \Ratchet\Client\WebSocket|null  $connection
     * @return string
     */
    public function subscribe(string $channel, ?array $data = [], ?string $auth = null, ?WebSocket $connection = null): string
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
     *
     * @return \Laravel\Reverb\Connection|null
     */
    public function managedConnection(): ?Connection
    {
        return connectionManager()->all()->last();
    }

    /**
     * Return a promise for the next message received to the given connection.
     *
     * @param  WebSocket  $connection
     * @return \React\Promise\PromiseInterface
     */
    public function messagePromise(WebSocket $connection): PromiseInterface
    {
        $promise = new Deferred;

        $connection->on('message', function ($message) use ($promise) {
            $promise->resolve((string) $message);
        });

        return $promise->promise();
    }
}
