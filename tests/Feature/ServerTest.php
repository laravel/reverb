<?php

use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Server;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);

    $this->server = $this->app->make(Server::class);
});

it('can handle a connection', function () {
    $this->server->open($connection = new Connection);

    $connection->assertSent([
        'event' => 'pusher:connection_established',
        'data' => json_encode([
            'socket_id' => '10000.00001',
            'activity_timeout' => 30,
        ]),
    ]);
});

it('can handle a disconnection', function () {
    $this->server->close(new Connection);

    $this->channelManager->shouldHaveReceived('unsubscribeFromAll');
});

it('can handle a new message', function () {
    $this->server->open($connection = new Connection);
    $this->server->message(
        $connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'test-channel',
                'auth' => '123',
            ],
        ]));

    $connection->assertSent([
        'event' => 'pusher:connection_established',
        'data' => json_encode([
            'socket_id' => '10000.00001',
            'activity_timeout' => 30,
        ]),
    ]);

    $connection->assertSent([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => json_encode([]),
        'channel' => 'test-channel',
    ]);
});

it('sends an error if something fails', function () {
    $this->server->message(
        $connection = new Connection,
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

    $connection->assertSent([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4200,
            'message' => 'Invalid message format',
        ]),
    ]);

    $connection->assertSent([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4009,
            'message' => 'Connection is unauthorized',
        ]),
    ]);
});

it('can subscribe a user to a channel', function () {
    $this->server->message(
        $connection = new Connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'test-channel',
                'auth' => '',
            ],
        ]));

    $connection->assertSent([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => json_encode([]),
        'channel' => 'test-channel',
    ]);
});

it('can subscribe a user to a private channel', function () {
    $this->server->message(
        $connection = new Connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'private-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', '10000.00001:private-test-channel', 'pusher-secret'),
            ],
        ]));

    $connection->assertSent([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => json_encode([]),
        'channel' => 'private-test-channel',
    ]);
});

it('can subscribe a user to a presence channel', function () {
    $this->channelManager->shouldReceive('connections')->andReturn(collect());
    $this->server->message(
        $connection = new Connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', '10000.00001:presence-test-channel', 'pusher-secret'),
            ],
        ]));

    $connection->assertSent([
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

it('unsubscribes a user from a channel on disconnection', function () {
    $this->server->message(
        $connection = new Connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'test-channel',
                'auth' => '',
            ],
        ]));

    $this->server->close($connection);

    $this->channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});

it('unsubscribes a user from a private channel on disconnection', function () {
    $this->server->message(
        $connection = new Connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'private-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', '10000.00001:private-test-channel', 'pusher-secret'),
            ],
        ]));

    $this->server->close($connection);

    $this->channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});

it('unsubscribes a user from a presence channel on disconnection', function () {
    $this->channelManager->shouldReceive('connections')->andReturn(collect());
    $this->server->message(
        $connection = new Connection,
        json_encode([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => 'presence-test-channel',
                'auth' => 'app-key:'.hash_hmac('sha256', '10000.00001:presence-test-channel', 'pusher-secret'),
            ],
        ]));

    $this->server->close($connection);

    $this->channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});
