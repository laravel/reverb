<?php

use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\ClientEvent;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->channelConnectionManager->shouldReceive('for')
        ->andReturn($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
    channels()->findOrCreate('test-channel');
});

it('can forward a client message', function () {
    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = factory());

    ClientEvent::handle(
        $this->connection, [
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
    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn([new ChannelConnection($this->connection)]);

    ClientEvent::handle(
        $this->connection, [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $this->connection->assertNothingReceived();
});

it('fails on unsupported message', function () {
    $this->channelConnectionManager->shouldNotReceive('hydratedConnections');

    ClientEvent::handle(
        $this->connection, [
            'event' => 'test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );
});
