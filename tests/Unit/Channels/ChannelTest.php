<?php

use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
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

it('can broadcast to all connections of a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('add');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(app(ApplicationProvider::class)->findByKey('pusher-key'), ['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});

it('does not broadcast to the connection sending the message', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->shouldReceive('add');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(app(ApplicationProvider::class)->findByKey('pusher-key'), ['foo' => 'bar'], $connections[0]->connection());

    $connections[0]->assertNothingSent();
    collect(array_slice($connections, -2))->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});
