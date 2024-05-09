<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Support\Str;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Servers\Reverb\Factory;
use React\Async\SimpleFiber;
use React\EventLoop\Loop;
use React\Http\Browser;
use React\Promise\PromiseInterface;
use ReflectionObject;

use function React\Async\await;

class ReverbTestCase extends TestCase
{
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
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('reverb.apps.apps.1', [
            'app_id' => '654321',
            'key' => 'reverb-key-2',
            'secret' => 'reverb-secret-2',
            'capacity' => null,
            'allowed_origins' => ['*'],
            'ping_interval' => 10,
            'max_message_size' => 1_000_000,
        ]);

        $app['config']->set('reverb.apps.apps.2', [
            'app_id' => '987654',
            'key' => 'reverb-key-3',
            'secret' => 'reverb-secret-3',
            'capacity' => null,
            'allowed_origins' => ['laravel.com'],
            'ping_interval' => 10,
            'max_message_size' => 1,
        ]);
    }

    /**
     * Configure the server to use Redis.
     */
    public function usingRedis(): void
    {
        app(ServerProviderManager::class)->withPublishing();

        app(PubSubProvider::class)->connect($this->loop);
        app(PubSubProvider::class)->subscribe();
    }

    /**
     * Start the WebSocket server.
     */
    public function startServer(string $host = '0.0.0.0', string $port = '8080', int $maxRequestSize = 10_000): void
    {
        $this->resetFiber();
        $this->server = Factory::make($host, $port, maxRequestSize: $maxRequestSize, loop: $this->loop);
    }

    /**
     * Reset the Fiber instance.
     * This prevents using a stale fiber between tests.
     */
    protected function resetFiber(): void
    {
        $fiber = new SimpleFiber();
        $fiberRef = new ReflectionObject($fiber);
        $scheduler = $fiberRef->getProperty('scheduler');
        $scheduler->setAccessible(true);
        $scheduler->setValue(null, null);
    }

    /**
     * Stop the running WebSocket server.
     */
    public function stopServer(): void
    {
        app(PubSubProvider::class)->disconnect();

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
            'data' => json_encode($data),
        ]));

        expect($response->getStatusCode())->toBe(200);
        expect($response->getBody()->getContents())->toBe('{}');
    }

    /**
     * Send a request to the server.
     */
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
     * Send a request to the server without specifying app ID.
     */
    public function requestWithoutAppId(string $path, string $method = 'GET', mixed $data = '', string $host = '0.0.0.0', string $port = '8080'): PromiseInterface
    {
        return (new Browser($this->loop))
            ->request(
                $method,
                "http://{$host}:{$port}/{$path}",
                [],
                ($data) ? json_encode($data) : ''
            );
    }

    /**
     * Send a signed request to the server.
     */
    public function signedRequest(string $path, string $method = 'GET', mixed $data = '', string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=reverb-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'reverb-secret');
        $path = Str::contains($path, '?') ? "{$path}&{$query}" : "{$path}?{$query}";

        return $this->request("{$path}&auth_signature={$signature}", $method, $data, $host, $port, $appId);
    }

    /**
     * Send a POST request to the server.
     */
    public function postRequest(string $path, ?array $data = [], string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        return $this->request($path, 'POST', $data, $host, $port, $appId);
    }

    /**
     * Send a signed POST request to the server.
     */
    public function signedPostRequest(string $path, ?array $data = [], string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=reverb-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'reverb-secret');

        return $this->postRequest("{$path}?{$query}&auth_signature={$signature}", $data, $host, $port, $appId);
    }

    /**
     * Send a signed GET request to the server.
     */
    public function getWithSignature(string $path, array $data = [], string $host = '0.0.0.0', string $port = '8080', string $appId = '123456'): PromiseInterface
    {
        $hash = md5(json_encode($data));
        $timestamp = time();
        $query = "auth_key=reverb-key&auth_timestamp={$timestamp}&auth_version=1.0&body_md5={$hash}";
        $string = "POST\n/apps/{$appId}/{$path}\n$query";
        $signature = hash_hmac('sha256', $string, 'reverb-secret');

        $path = Str::contains($path, '?') ? "{$path}&{$query}" : "{$path}?{$query}";

        return $this->request("{$path}&auth_signature={$signature}", 'GET', '', $host, $port, $appId);
    }
}
