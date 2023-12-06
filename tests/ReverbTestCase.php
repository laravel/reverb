<?php

namespace Laravel\Reverb\Tests;

use Clue\React\Redis\Client;
use Illuminate\Support\Str;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Event;
use Laravel\Reverb\ServerManager;
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

class ReverbTestCase extends TestCase
{
    use InteractsWithAsyncRedis;

    protected $server;

    protected $loop;

    protected $connectionId;

    protected function setUp(): void
    {
        parent::setUp();

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
            'max_message_size' => 1000000,
        ]);

        $app['config']->set('reverb.apps.apps.2', [
            'id' => '987654',
            'key' => 'pusher-key-3',
            'secret' => 'pusher-secret-3',
            'capacity' => null,
            'allowed_origins' => ['laravel.com'],
            'ping_interval' => 10,
            'max_message_size' => 1,
        ]);
    }

    public function usingRedis()
    {
        app(ServerManager::class)->withPublishing();

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
     * Send an event to the server.
     */
    public function triggerEvent(string $channel, string $event, array $data = []): void
    {
        $response = await($this->signedPostRequest('events', [
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

    public function signedRequest(string $path, string $method = 'GET', mixed $data = '', string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=pusher-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'pusher-secret');
        $path = Str::contains($path, '?') ? "{$path}&{$query}" : "{$path}?{$query}";

        return $this->request("{$path}&auth_signature={$signature}", $method, $data, $host, $port, $appId);
    }

    /**
     * Post a request to the server.
     */
    public function postReqeust(string $path, array $data = [], string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        return $this->request($path, 'POST', $data, $host, $port, $appId);
    }

    /**
     * Post a signed request to the server.
     */
    public function signedPostRequest(string $path, array $data = [], string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=pusher-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'pusher-secret');

        return $this->postReqeust("{$path}?{$query}&auth_signature={$signature}", $data, $host, $port, $appId);
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
