<?php

use Laravel\Reverb\ClientEvent;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->channelManager->shouldReceive('for')
        ->andReturn($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('can forward a client message', function () {
    $this->channelManager->shouldReceive('hydratedConnections')
        ->once()
        ->andReturn($connections = connections());

    ClientEvent::handle(
        $this->connection, [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $connections->first()->assertSent([
        'event' => 'client-test-message',
        'channel' => 'test-channel',
        'data' => ['foo' => 'bar'],
    ]);
});

it('does not forward a message to itself', function () {
    $this->channelManager->shouldReceive('hydratedConnections')
        ->once()
        ->andReturn(collect());

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
    $this->channelManager->shouldNotReceive('hydratedConnections');

    ClientEvent::handle(
        $this->connection, [
            'event' => 'test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );
});
