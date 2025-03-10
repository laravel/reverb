<?php

use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\Server;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->server = $this->app->make(Server::class);
});

it('can handle a connection', function () {
    $this->server->open($connection = new FakeConnection);

    expect($connection->lastSeenAt())->not->toBeNull();

    $connection->assertReceived([
        'event' => 'pusher:connection_established',
        'data' => json_encode([
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]),
    ]);
});

it('can handle a disconnection', function () {
    $channelManager = Mockery::spy(ChannelManager::class);
    $channelManager->shouldReceive('for')
        ->andReturn($channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $channelManager);
    $server = $this->app->make(Server::class);

    $server->close(new FakeConnection);

    $channelManager->shouldHaveReceived('unsubscribeFromAll');
});

it('can handle a new message', function () {
    $this->server->open($connection = new FakeConnection);
    $this->server->message(
        $connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'test-channel',
                'auth' => '123',
            ],
        ]));

    $connection->assertReceived([
        'event' => 'pusher:connection_established',
        'data' => json_encode([
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]),
    ]);

    $connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
        'channel' => 'test-channel',
    ]);
});

it('sends an error if something fails', function () {
    $this->server->message(
        $connection = new FakeConnection,
        'Hi'
    );

    $this->server->message(
        $connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'private-test-channel',
                'auth' => '123',
            ],
        ]));

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4009,
            'message' => 'Connection is unauthorized',
        ]),
    ]);
});

it('can subscribe a user to a channel', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'test-channel',
                'auth' => '',
            ],
        ]));

    expect($connection->lastSeenAt())->not->toBeNull();

    $connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
        'channel' => 'test-channel',
    ]);
});

it('can subscribe a user to a private channel', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'private-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', $connection->id().':private-test-channel', 'reverb-secret'),
            ],
        ]));

    $connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
        'channel' => 'private-test-channel',
    ]);
});

it('can subscribe a user to a presence channel', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', $connection->id().':presence-test-channel', 'reverb-secret'),
            ],
        ]));

    $connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => json_encode([
            'presence' => [
                'count' => 0,
                'ids' => [],
                'hash' => [],
            ],
        ]),
        'channel' => 'presence-test-channel',
    ]);
});

it('receives no data when no previous event triggered when joining a cache channel', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'cache-test-channel',
            ],
        ]));

    $connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
        'channel' => 'cache-test-channel',
    ]);
    $connection->assertReceived([
        'event' => 'pusher:cache_miss',
        'channel' => 'cache-test-channel',
    ]);
    $connection->assertReceivedCount(2);
});

it('receives last triggered event when joining a cache channel', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'cache-test-channel',
            ],
        ]));

    $channel = app(ChannelManager::class)->find('cache-test-channel');

    $channel->broadcast(['foo' => 'bar']);

    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'cache-test-channel',
            ],
        ]));

    $connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
        'channel' => 'cache-test-channel',
    ]);
    $connection->assertReceived(['foo' => 'bar']);
    $connection->assertReceivedCount(2);
});

it('unsubscribes a user from a channel on disconnection', function () {
    $channelManager = Mockery::spy(ChannelManager::class);
    $channelManager->shouldReceive('for')
        ->andReturn($channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $channelManager);
    $server = $this->app->make(Server::class);

    $server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'test-channel',
                'auth' => '',
            ],
        ]));

    $server->close($connection);

    $channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});

it('unsubscribes a user from a private channel on disconnection', function () {
    $channelManager = Mockery::spy(ChannelManager::class);
    $channelManager->shouldReceive('for')
        ->andReturn($channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $channelManager);
    $server = $this->app->make(Server::class);

    $server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'private-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', '10000.00001:private-test-channel', 'reverb-secret'),
            ],
        ]));

    $server->close($connection);

    $channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});

it('unsubscribes a user from a presence channel on disconnection', function () {
    $channelManager = Mockery::spy(ChannelManager::class);
    $channelManager->shouldReceive('for')
        ->andReturn($channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $channelManager);
    $server = $this->app->make(Server::class);

    $server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', '10000.00001:presence-test-channel', 'reverb-secret'),
            ],
        ]));

    $server->close($connection);

    $channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});

it('it rejects a connection from an invalid origin', function (string $origin, array $allowedOrigins) {
    $this->app['config']->set('reverb.apps.apps.0.allowed_origins', $allowedOrigins);
    $this->server->open($connection = new FakeConnection(origin: $origin));

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4009,
            'message' => 'Origin not allowed',
        ]),
    ]);
})->with([
    'localhost' => [
        'http://localhost',
        ['laravel.com'],
    ],
    'subdomain' => [
        'http://sub.laravel.com',
        ['laravel.com'],
    ],
    'wildcard' => [
        'http://laravel.com',
        ['*.laravel.com'],
    ],
]);

it('accepts a connection from an valid origin', function (string $origin, array $allowedOrigins) {
    $this->app['config']->set('reverb.apps.apps.0.allowed_origins', $allowedOrigins);
    $this->server->open($connection = new FakeConnection(origin: $origin));

    $connection->assertReceived([
        'event' => 'pusher:connection_established',
        'data' => json_encode([
            'socket_id' => $connection->id(),
            'activity_timeout' => 30,
        ]),
    ]);
})->with([
    'localhost' => [
        'http://localhost',
        ['localhost'],
    ],
    'wildcard' => [
        'http://sub.localhost',
        ['localhost', '*.localhost'],
    ],
]);

it('sends an error if something fails for event type', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => [],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);
});

it('sends an error if something fails for data type', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => 'sfsfsfs',
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);
});

it('sends an error if something fails for data channel type', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => [],
            ],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);

    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => null,
            ],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);
});

it('sends an error if something fails for data auth type', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => [],
            ],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);
});

it('sends an error if something fails for data channel_data type', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => '',
                'channel_data' => [],
            ],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);

    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => '',
                'channel_data' => 'Hello',
            ],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);
});

it('sends an error if something fails for channel type', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'client-start-typing',
            'channel' => [],
        ])
    );

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);
});

it('allow receiving client event with empty data', function () {
    $this->server->message(
        $connection = new FakeConnection,
        json_encode([
            'event' => 'client-start-typing',
            'channel' => 'private-chat.1',
        ])
    );

    $connection->assertNothingReceived();
});
