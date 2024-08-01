<?php

use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->channelConnectionManager->shouldReceive('for')
        ->andReturn($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection);
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('removes a channel when no subscribers remain', function () {
    $channelManager = Mockery::spy(ChannelManager::class);
    $this->app->instance(ChannelManager::class, $channelManager);

    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);
    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);
    $this->channelConnectionManager->shouldReceive('isEmpty')
        ->once()
        ->andReturn(true);
    $channelManager->shouldReceive('for')
        ->once()
        ->andReturn($channelManager);
    $channelManager->shouldReceive('remove')
        ->once()
        ->with($channel);

    $channel->subscribe($this->connection);
    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('add');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = factory(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});

it('does not broadcast to the connection sending the message', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('add');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = factory(3));

    $channel->broadcast(['foo' => 'bar'], collect($connections)->first()->connection());

    collect($connections)->first()->assertNothingReceived();
    collect(array_slice($connections, -2))->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});
