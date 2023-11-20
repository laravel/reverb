<?php

use Laravel\Reverb\ClientEvent;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can forward a client message', function () {
    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = connections());

    ClientEvent::handle(
        $this->connection, [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $connections[0]->assertSent([
        'event' => 'client-test-message',
        'channel' => 'test-channel',
        'data' => ['foo' => 'bar'],
    ]);
});

it('does not forward a message to itself', function () {
    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn([$this->connection]);

    ClientEvent::handle(
        $this->connection, [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $this->connection->assertNothingSent();
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
