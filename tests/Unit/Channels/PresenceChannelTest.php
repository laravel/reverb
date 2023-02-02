<?php

use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\PresenceChannel;
use Laravel\Reverb\Contracts\ApplicationsProvider;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Managers\Connections;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->channelManager->shouldReceive('for')
        ->andReturn($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, []);

    $this->channelManager->shouldReceive('connections')
        ->andReturn(Connections::make());

    $channel->subscribe($this->connection, validAuth($this->connection, 'presence-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelManager->shouldReceive('unsubscribe')
        ->once()
        ->with($channel, $this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelManager->shouldReceive('subscribe');

    $this->channelManager->shouldReceive('connections')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(app(ApplicationsProvider::class)->findByKey('pusher-key'), ['foo' => 'bar']);

    $connections->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('can return data stored on the connection', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connections = connections(2, )
        ->map(fn ($connection, $index) => [
            'user_info' => [
                'name' => 'Joe',
            ],
            'user_id' => $index + 1,
        ]);

    $this->channelManager->shouldReceive('connectionKeys')
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

    $this->channelManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, []);

    $this->channelManager->shouldReceive('connections')
        ->andReturn($connections = connections(3));

    $channel->subscribe($this->connection, validAuth($this->connection, 'presence-test-channel'));

    $connections->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_added',
        'data' => [],
        'channel' => 'presence-test-channel',
    ]));
});

it('sends notification of subscription with data', function () {
    $channel = new PresenceChannel('presence-test-channel');
    $data = json_encode(['name' => 'Joe']);

    $this->channelManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, ['name' => 'Joe']);

    $this->channelManager->shouldReceive('connections')
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
});

it('sends notification of an unsubscribe', function () {
    $channel = new PresenceChannel('presence-test-channel');
    $connection = $connection = connections(1)->first();

    $this->channelManager->shouldReceive('data')
        ->andReturn(['user_info' => ['name' => 'Joe'], 'user_id' => 1]);

    $this->channelManager->shouldReceive('connections')
        ->andReturn($connections = connections(3));

    $this->channelManager->shouldReceive('unsubscribe');

    $channel->unsubscribe($this->connection);

    $connections->each(fn ($connection) => $connection->assertSent([
        'event' => 'pusher_internal:member_removed',
        'data' => ['user_id' => 1],
        'channel' => 'presence-test-channel',
    ]));
});
