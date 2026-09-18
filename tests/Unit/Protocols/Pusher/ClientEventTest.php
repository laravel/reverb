<?php

use JMac\Testing\Double;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\ClientEvent;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->channelConnectionManager = Double::for(ChannelConnectionManager::class);
    $this->channelConnectionManager->expects('for')->returns($this->channelConnectionManager);

    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can forward a client message', function () {
    channels()->findOrCreate('private-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '1']))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '2']))->first();

    $this->channelConnectionManager->expects('find')->returns($connectionOne);
    $this->channelConnectionManager->expects('all')->returns([$connectionOne, $connectionTwo]);

    ClientEvent::handle(
        $connectionOne->connection(), [
            'event' => 'client-test-message',
            'channel' => 'private-test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $connectionOne->connection()->assertNothingReceived();
    $connectionTwo->connection()->assertReceived([
        'event' => 'client-test-message',
        'channel' => 'private-test-channel',
        'data' => ['foo' => 'bar'],
        'user_id' => '1',
    ]);
});

it('can forward an unauthenticated client message on public channel', function () {
    channels()->findOrCreate('test-channel');

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $this->channelConnectionManager->expects('find')->returns($connections[0]);

    ClientEvent::handle(
        $connections[0]->connection(), [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    foreach ($connections as $i => $connection) {
        if ($i == 0) {
            $connection->connection()->assertNothingReceived();
        } else {
            $connection->connection()->assertReceived([
                'event' => 'client-test-message',
                'channel' => 'test-channel',
                'data' => ['foo' => 'bar'],
            ]);
        }

    }
});

it('does not forward unauthenticated client message when in members mode', function () {
    channels()->findOrCreate('private-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '1']))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '2']))->first();

    $this->channelConnectionManager->expects('find')->returns(null);

    ClientEvent::handle(
        $connectionOne->connection(), [
            'event' => 'client-test-message',
            'channel' => 'private-test-channel',
            'data' => ['foo' => 'bar'],
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

it('does not forward client message when set to none', function () {
    $this->app['config']->set('reverb.apps.apps.0.accept_client_events_from', 'none');
    channels()->findOrCreate('private-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '1']))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => '2']))->first();

    ClientEvent::handle(
        $connectionOne->connection(), [
            'event' => 'client-test-message',
            'channel' => 'private-test-channel',
            'data' => ['foo' => 'bar'],
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

it('forwards a client message for unauthenticated client when set to all', function () {
    $this->app['config']->set('reverb.apps.apps.0.accept_client_events_from', 'all');
    $connection = new FakeConnection;
    channels()->findOrCreate('test-channel');

    $this->channelConnectionManager->expects('all')->returns($connections = factory());

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

    $this->channelConnectionManager->expects('all')->returns([$connection]);
    $this->channelConnectionManager->expects('find')->returns($connection);

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

    $this->channelConnectionManager->expects('all')->never();

    ClientEvent::handle(
        $connection, [
            'event' => 'test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );
});
