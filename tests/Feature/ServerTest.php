<?php

use Illuminate\Testing\Assert;
use Reverb\Contracts\ChannelManager;
use Reverb\Contracts\Connection as ConnectionInterface;
use Reverb\Contracts\ConnectionManager;
use Reverb\Server;

beforeEach(function () {
    $this->connectionManager = Mockery::spy(ConnectionManager::class);
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->app->singleton(ConnectionManager::class, fn () => $this->connectionManager);
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

    $this->connectionManager->shouldHaveReceived('disconnect');
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

it('removes a connection from the manager on disconnection', function () {
    $this->server->close($connection = new Connection);

    $this->connectionManager->shouldHaveReceived('disconnect')
        ->once()
        ->with($connection);
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

    $this->connectionManager->shouldHaveReceived('disconnect')
        ->once()
        ->with($connection);
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

    $this->connectionManager->shouldHaveReceived('disconnect')
        ->once()
        ->with($connection);
    $this->channelManager->shouldHaveReceived('unsubscribeFromAll')
        ->once()
        ->with($connection);
});

it('unsubscribes a user from a presence channel on disconnection', function () {
    //
})->skip();

it('notifies all connections on subscription to a presence channel', function () {
    //
})->skip();

it('notifies all connections on unsubscribe from a presence channel', function () {
    //
})->skip();

class Connection implements ConnectionInterface
{
    public $messages = [];

    public function identifier(): string
    {
        return '19c1c8e8-351b-4eb5-b6d9-6cbfc54a3446';
    }

    public function id(): string
    {
        return '10000.00001';
    }

    public function send(string $message): void
    {
        dump($message);
        $this->messages[] = $message;
    }

    public function assertSent(array $message): void
    {
        dump(json_encode($message));
        Assert::assertContains(json_encode($message), $this->messages);
    }
}
