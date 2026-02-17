<?php

use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\ClientEvent;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->channelConnectionManager->shouldReceive('for')
        ->andReturn($this->channelConnectionManager);

    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can forward a client message', function () {
    channels()->findOrCreate('private-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '1']))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '2']))->first();

    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn($connectionOne);
    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([$connectionOne, $connectionTwo]);

    ClientEvent::handle(
        $connectionOne->connection(), [
            'event' => 'client-test-message',
            'data' => ['foo' => 'bar'],
            'channel' => 'private-test-channel',
        ]
    );

    $connectionOne->connection()->assertNothingReceived();
    $connectionTwo->connection()->assertReceived([
        'event' => 'client-test-message',
        'data' => ['foo' => 'bar'],
        'channel' => 'private-test-channel',
        'user_id' => '1',
    ]);
});

it('can forward an unauthenticated client message on public channel', function () {
    channels()->findOrCreate('test-channel');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = factory(3));

    $this->channelConnectionManager->shouldReceive('find')
        ->once()
        ->andReturn($connections[0]);

    ClientEvent::handle(
        $connections[0]->connection(), [
            'event' => 'client-test-message',
            'data' => ['foo' => 'bar'],
            'channel' => 'test-channel',
        ]
    );

    foreach ($connections as $i => $connection) {
        if ($i == 0) {
            $connection->connection()->assertNothingReceived();
        } else {
            $connection->connection()->assertReceived([
                'event' => 'client-test-message',
                'data' => ['foo' => 'bar'],
                'channel' => 'test-channel'
            ]);
        }

    }
});

it('does not forward unauthenticated client message when in member mode', function () {
    channels()->findOrCreate('private-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '1']))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '2']))->first();

    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn(null);
    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([$connectionTwo]);

    ClientEvent::handle(
        $connectionOne->connection(), [
            'event' => 'client-test-message',
            'data' => ['foo' => 'bar'],
            'channel' => 'private-test-channel',
        ]
    );

    $connectionOne->connection()->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4009,
            'message' => 'The client is not a member of the specified channel.',
        ]),
    ]);
    $connectionTwo->connection()->assertNothingReceived();
});

it('does not forward client message when disabled', function () {
    $this->app['config']->set('reverb.apps.apps.0.client_events_mode', 'disabled');
    channels()->findOrCreate('private-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '1']))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '2']))->first();

    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn($connectionOne);
    $this->channelConnectionManager->shouldReceive('all')
        ->andReturn([$connectionOne, $connectionTwo]);

    ClientEvent::handle(
        $connectionOne->connection(), [
            'event' => 'client-test-message',
            'data' => ['foo' => 'bar'],
            'channel' => 'private-test-channel',
        ]
    );

    $connectionOne->connection()->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => 4301,
            'message' => 'The app does not have client messaging enabled.',
        ]),
    ]);
    $connectionTwo->connection()->assertNothingReceived();
});


it('forwards a client message for unauthenticated client when in unauthenticated mode', function () {
    $this->app['config']->set('reverb.apps.apps.0.client_events_mode', 'unauthenticated');
    $connection = new FakeConnection;
    channels()->findOrCreate('test-channel');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = factory());

    ClientEvent::handle(
        $connection, [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    collect($connections)->first()->assertReceived([
        'event' => 'client-test-message',
        'channel' => 'test-channel',
        'data' => ['foo' => 'bar'],
    ]);
});

it('does not forward a message to itself', function () {
    $connection = new ChannelConnection(new FakeConnection);
    channels()->findOrCreate('test-channel');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn([$connection]);
    $this->channelConnectionManager->shouldReceive('find')
        ->andReturn($connection);

    ClientEvent::handle(
        $connection->connection(), [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $connection->connection()->assertNothingReceived();
});

it('fails on unsupported message', function () {
    channels()->findOrCreate('test-channel');

    $connection = new FakeConnection;

    $this->channelConnectionManager->shouldNotReceive('hydratedConnections');

    ClientEvent::handle(
        $connection, [
            'event' => 'test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );
});
