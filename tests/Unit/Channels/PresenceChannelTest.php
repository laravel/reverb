<?php

use Laravel\Reverb\Channels\PresenceChannel;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Managers\Connections;
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

    $channel->subscribe($this->connection, validAuth($this->connection, 'presence-test-channel'));
})->todo();

it('can unsubscribe a connection from a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);
})->todo();

it('can broadcast to all connections of a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('subscribe');

    $this->channelConnectionManager->shouldReceive('connections')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(app(ApplicationProvider::class)->findByKey('pusher-key'), ['foo' => 'bar']);

    $connections->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
})->todo();

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('can return data stored on the connection', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connections = connections(2)
        ->map(fn ($connection, $index) => [
            'user_info' => [
                'name' => 'Joe',
            ],
            'user_id' => $index + 1,
        ]);

    $this->channelConnectionManager->shouldReceive('connectionKeys')
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
})->todo();

it('sends notification of subscription', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, []);

    $this->channelConnectionManager->shouldReceive('connections')
        ->andReturn($connections = connections(3));

    $channel->subscribe($this->connection, validAuth($this->connection, 'presence-test-channel'));

    $connections->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_added',
        'data' => [],
        'channel' => 'presence-test-channel',
    ]));
})->todo();

it('sends notification of subscription with data', function () {
    $channel = new PresenceChannel('presence-test-channel');
    $data = json_encode(['name' => 'Joe']);

    $this->channelConnectionManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, ['name' => 'Joe']);

    $this->channelConnectionManager->shouldReceive('connections')
        ->andReturn($connections = connections(3));

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection,
            'presence-test-channel',
            $data
        ),
        $data
    );

    $connections->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_added',
        'data' => ['name' => 'Joe'],
        'channel' => 'presence-test-channel',
    ]));
})->todo();

it('sends notification of an unsubscribe', function () {
    $channel = new PresenceChannel('presence-test-channel');
    $connection = $connection = connections(1)->first();

    $this->channelConnectionManager->shouldReceive('data')
        ->andReturn(['user_info' => ['name' => 'Joe'], 'user_id' => 1]);

    $this->channelConnectionManager->shouldReceive('connections')
        ->andReturn($connections = connections(3));

    $this->channelConnectionManager->shouldReceive('unsubscribe');

    $channel->unsubscribe($this->connection);

    $connections->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_removed',
        'data' => ['user_id' => 1],
        'channel' => 'presence-test-channel',
    ]));
})->todo();
