<?php

namespace Laravel\Reverb\Tests;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\NullLogger;
use Laravel\Reverb\ManagerProvider;
use Laravel\Reverb\Servers\ApiGateway\Jobs\SendToConnection;
use Laravel\Reverb\Servers\ApiGateway\Request;
use Laravel\Reverb\Servers\ApiGateway\Server;
use Laravel\Reverb\ReverbServiceProvider;

class ApiGatewayTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(Logger::class, new NullLogger);
    }

    /**
     * Resolve application core configuration implementation.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function resolveApplicationConfiguration($app)
    {
        parent::resolveApplicationConfiguration($app);
        $app['config']->set('reverb.default', 'api_gateway');
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
            ReverbServiceProvider::class,
            ManagerProvider::class,
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
        $app['config']->set('reverb.apps.apps.1', [
            'id' => '654321',
            'key' => 'pusher-key-2',
            'secret' => 'pusher-secret-2',
            'capacity' => null,
            'allowed_origins' => ['*'],
            'ping_interval' => 10,
            'max_message_size' => 1000000,
        ]);
    }

    /**
     * Connect to the server.
     */
    public function connect(string $connectionId = 'abc-123', string $appKey = 'pusher-key'): void
    {
        resolve(Server::class)
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
     */
    public function send(array $message, ?string $connectionId = 'abc-123', string $appKey = 'pusher-key'): void
    {
        $this->connect($connectionId, $appKey);

        resolve(Server::class)
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
     */
    public function subscribe(string $channel, ?array $data = [], ?string $auth = null, ?string $connectionId = 'abc-123', string $appKey = 'pusher-key'): void
    {
        $data = ! empty($data) ? json_encode($data) : null;

        if (! $auth && Str::startsWith($channel, ['private-', 'presence-'])) {
            $this->connect($connectionId, $appKey);
            $managed = $this->managedConnection();
            $auth = validAuth($managed->id(), $channel, $data);
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
     */
    public function disconnect(string $connectionId = 'abc-123'): void
    {
        resolve(Server::class)
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
     */
    public function assertSent(?string $connectionId = null, mixed $message = null, ?int $times = null): void
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
     */
    public function managedConnection(): ?Connection
    {
        $connection = Arr::last(connections()->all());

        return connections()->find($connection->identifier());
    }
}
