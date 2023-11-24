<?php

use Laravel\Reverb\Channels\PresenceChannel;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Servers\Reverb\ChannelConnection;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once($this->connection, []);

    $this->channelConnectionManager->shouldReceive('connections')
        ->andReturn([]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('subscribe');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('can return data stored on the connection', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connections = [
        connections(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1])[0],
        connections(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 2])[0],
    ];

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections);

    expect($channel->data($this->connection->app()))->toBe([
        'presence' => [
            'count' => 2,
            'ids' => [1, 2],
            'hash' => [
                1 => ['name' => 'Joe'],
                2 => ['name' => 'Joe'],
            ],
        ],
    ]);
});

it('sends notification of subscription', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn($connections = connections(3));

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel'));

    collect($connections)->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_added',
        'data' => [],
        'channel' => 'presence-test-channel',
    ]));
});

it('sends notification of subscription with data', function () {
    $channel = new PresenceChannel('presence-test-channel');
    $data = json_encode(['name' => 'Joe']);

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, ['name' => 'Joe']);

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn($connections = connections(3));

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-test-channel',
            $data
        ),
        $data
    );

    collect($connections)->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_added',
        'data' => ['name' => 'Joe'],
        'channel' => 'presence-test-channel',
    ]));
});

it('sends notification of an unsubscribe', function () {
    $channel = new PresenceChannel('presence-test-channel');
    $data = json_encode(['user_info' => ['name' => 'Joe'], 'user_id' => 1]);

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-test-channel',
            $data
        ),
        $data
    );

    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn(new ChannelConnection($this->connection, ['user_info' => ['name' => 'Joe'], 'user_id' => 1]));

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn($connections = connections(3));

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);

    collect($connections)->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_removed',
        'data' => ['user_id' => 1],
        'channel' => 'presence-test-channel',
    ]));
});
