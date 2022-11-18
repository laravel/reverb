<?php

use Laravel\Reverb\ClientEvent;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('can forward a client message', function () {
    $this->channelManager->shouldReceive('connections')
        ->once()
        ->andReturn($connections = connections());

    ClientEvent::handle(
        $this->connection, [
            'event' => 'client-test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );

    $connections->first()['connection']->assertSent([
        'event' => 'client-test-message',
        'channel' => 'test-channel',
        'data' => ['foo' => 'bar'],
        'except' => $this->connection->identifier(),
    ]);
});

it('does not forward a message to itself', function () {
    $this->channelManager->shouldReceive('connections')
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
    $this->channelManager->shouldNotReceive('connections');

    ClientEvent::handle(
        $this->connection, [
            'event' => 'test-message',
            'channel' => 'test-channel',
            'data' => ['foo' => 'bar'],
        ]
    );
});
