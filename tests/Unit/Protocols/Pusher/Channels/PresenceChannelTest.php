<?php

use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Channels\PresenceChannel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Tests\FakeConnection;
use Illuminate\Support\Facades\Event;
use Laravel\Reverb\Events\PresenceChannelSubscribe;
use Laravel\Reverb\Events\PresenceChannelUnsubscribe;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->channelConnectionManager->shouldReceive('for')
        ->andReturn($this->channelConnectionManager);
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
        ->andReturn($connections = factory(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('can return data stored on the connection', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connections = [
        collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first(),
        collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 2]))->first(),
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


it('dispatches PresenceChannelSubscribe event on first subscription', function () {
    Event::fake([PresenceChannelSubscribe::class]);

    $channel = new PresenceChannel('presence-test-channel');
    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, ['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([]);

    $channel->subscribe(
        $this->connection,
        validAuth($this->connection->id(), 'presence-test-channel', $data),
        $data
    );

    Event::assertDispatched(PresenceChannelSubscribe::class, function ($event) {
        return $event->channel === 'presence-test-channel' &&
               $event->connection === $this->connection;
    });
});

it('dispatches PresenceChannelUnsubscribe event when last connection of user is removed', function () {
    Event::fake([PresenceChannelUnsubscribe::class]);

    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn(new ChannelConnection($this->connection, [
            'user_id' => 1,
            'user_info' => ['name' => 'Joe'],
        ]));

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([]);

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);

    Event::assertDispatched(PresenceChannelUnsubscribe::class, function ($event) {
        return $event->channel === 'presence-test-channel' &&
               $event->connection === $this->connection;
    });
});

it('sends notification of subscription', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn($connections = factory(3));

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel'));

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_added',
        'data' => '{}',
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
        ->andReturn($connections = factory(3));

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-test-channel',
            $data
        ),
        $data
    );

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_added',
        'data' => json_encode(['name' => 'Joe']),
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
        ->andReturn($connections = factory(3));

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_removed',
        'data' => json_encode(['user_id' => 1]),
        'channel' => 'presence-test-channel',
    ]));
});

it('ensures the "member_added" event is only fired once', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([$connectionOne, $connectionTwo]);

    $channel->subscribe($connectionOne->connection(), validAuth($connectionOne->id(), 'presence-test-channel', $data = json_encode($connectionOne->data())), $data);
    $channel->subscribe($connectionTwo->connection(), validAuth($connectionTwo->id(), 'presence-test-channel', $data = json_encode($connectionTwo->data())), $data);

    $connectionOne->connection()->assertNothingReceived();
});

it('ensures the "member_removed" event is only fired once', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn($connectionOne);

    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([$connectionOne, $connectionTwo]);

    $channel->unsubscribe($connectionTwo->connection(), validAuth($connectionTwo->id(), 'presence-test-channel', $data = json_encode($connectionTwo->data())), $data);

    $connectionOne->connection()->assertNothingReceived();
});
