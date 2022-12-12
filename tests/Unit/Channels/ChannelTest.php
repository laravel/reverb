<?php

use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->channelManager->shouldReceive('for')
        ->andReturn($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelManager->shouldReceive('subscribe')
        ->once()
        ->with($channel, $this->connection, []);

    $channel->subscribe($this->connection);
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelManager->shouldReceive('unsubscribe')
        ->once()
        ->with($channel, $this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelManager->shouldReceive('subscribe');

    $this->channelManager->shouldReceive('connections')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(Application::findByKey('pusher-key'), ['foo' => 'bar']);

    $connections->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});

it('does not broadcast to the connection sending the message', function () {
    $channel = new Channel('test-channel');

    $this->channelManager->shouldReceive('subscribe');

    $this->channelManager->shouldReceive('connections')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(Application::findByKey('pusher-key'), ['foo' => 'bar'], $connections->first());

    $connections->first()->assertNothingSent();
    $connections->take(-2)->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});
