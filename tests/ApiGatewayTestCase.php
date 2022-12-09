<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\NullLogger;
use Laravel\Reverb\Servers\ApiGateway\Jobs\SendToConnection;
use Laravel\Reverb\Servers\ApiGateway\Request;
use Laravel\Reverb\Servers\ApiGateway\Server;
use Laravel\Reverb\Servers\ApiGateway\ServiceProvider as ApiGatewayServiceProvider;
use Laravel\Reverb\ServiceProvider;

class ApiGatewayTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Logger::class, new NullLogger);
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array<int, class-string<\Illuminate\Support\ServiceProvider>>
     */
    protected function getPackageProviders($app)
    {
        return [
            ServiceProvider::class,
            ApiGatewayServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app)
    {
        $app['config']->set('reverb.default', 'api_gatway');

        $app['config']->set('reverb.apps.1', [
            'id' => '654321',
            'key' => 'pusher-key-2',
            'secret' => 'pusher-secret-2',
            'capacity' => null,
            'allowed_origins' => ['*'],
            'ping_interval' => 10,
        ]);
    }

    /**
     * Connect to the server.
     *
     * @param  string  $connectionId $name
     * @param  string  $appKey
     * @return void
     */
    public function connect($connectionId = 'abc-123', $appKey = 'pusher-key'): void
    {
        App::make(Server::class)
            ->handle(Request::fromLambdaEvent(
                [
                    'requestContext' => [
                        'eventType' => 'CONNECT',
                        'connectionId' => $connectionId,
                    ],
                    'queryStringParameters' => [
                        'appId' => $appKey,
                    ],
                ]
            ));

        $this->assertSent($connectionId, 'connection_established');
    }

    /**
     * Send a message to the connected client.
     *
     * @param  array  $message
     * @param  string  $connectionId
     * @param  string  $appKey
     * @return void
     */
    public function send(array $message, ?string $connectionId = 'abc-123', $appKey = 'pusher-key'): void
    {
        $this->connect($connectionId, $appKey);

        App::make(Server::class)
            ->handle(Request::fromLambdaEvent(
                [
                    'requestContext' => [
                        'eventType' => 'MESSAGE',
                        'connectionId' => $connectionId,
                    ],
                    'body' => json_encode($message),
                ]
            ));
    }

    /**
     * Subscribe to a channel.
     *
     * @param  string  $channel
     * @param  array  $data
     * @param  string|null  $auth
     * @param  string  $connectionId
     * @param  string  $appKey
     * @return void
     */
    public function subscribe(string $channel, ?array $data = [], ?string $auth = null, ?string $connectionId = 'abc-123', $appKey = 'pusher-key'): void
    {
        $data = ! empty($data) ? json_encode($data) : null;

        if (! $auth && Str::startsWith($channel, ['private-', 'presence-'])) {
            $this->connect($connectionId, $appKey);
            $managed = $this->managedConnection();
            $auth = validAuth($managed, $channel, $data);
        }

        $this->send([
            'event' => 'pusher:subscribe',
            'data' => array_filter([
                'channel' => $channel,
                'channel_data' => $data,
                'auth' => $auth,
            ]),
        ], $connectionId);
    }

    /**
     * Disconnect a connected client.
     *
     * @param  string  $connectionId
     * @return void
     */
    public function disconnect($connectionId = 'abc-123'): void
    {
        App::make(Server::class)
            ->handle(Request::fromLambdaEvent(
                [
                    'requestContext' => [
                        'eventType' => 'DISCONNECT',
                        'connectionId' => $connectionId,
                    ],
                ]
            ));
    }

    /**
     * Assert a message was sent to the given connection.
     *
     * @param  string|null  $connectionId
     * @param  mixed  $message
     * @param  int|null  $times
     * @return void
     */
    public function assertSent(string $connectionId = null, mixed $message = null, ?int $times = null)
    {
        Bus::assertDispatched(SendToConnection::class, function ($job) use ($connectionId, $message) {
            return ($connectionId ? $job->connectionId === $connectionId : true)
                && ($message ? Str::contains($job->message, (array) $message) : true);
        });

        if ($times) {
            Bus::assertDispatchedTimes(SendToConnection::class, $times);
        }

        Bus::fake();
    }

    /**
     * Return the latest connection set on the manager.
     *
     * @return \Laravel\Reverb\Connection|null
     */
    public function managedConnection(): ?Connection
    {
        return Connection::hydrate(connectionManager()->all()->last());
    }
}
