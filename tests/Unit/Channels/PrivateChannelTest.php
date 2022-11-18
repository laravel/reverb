<?php

use Laravel\Reverb\Channels\PrivateChannel;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PrivateChannel('private-test-channel');

    $this->channelManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, []);

    $this->channelManager->shouldReceive('connections')
        ->andReturn(collect());

    $channel->subscribe($this->connection, validAuth($this->connection, 'private-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PrivateChannel('private-test-channel');

    $this->channelManager->shouldReceive('unsubscribe')
        ->once()
        ->with($channel, $this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PrivateChannel('test-channel');

    $this->channelManager->shouldReceive('subscribe');

    $this->channelManager->shouldReceive('connections')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(['foo' => 'bar']);

    $connections->each(fn ($connection) => $connection['connection']->assertSent(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PrivateChannel('presence-test-channel');

    $this->channelManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);
