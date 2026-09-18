<?php

use JMac\Testing\Double;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Double::for(ChannelConnectionManager::class);
    $this->channelConnectionManager->allows('for')->returns($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->expects('add')->with($this->connection, []);

    $channel->subscribe($this->connection);
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->expects('remove')->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('removes a channel when no subscribers remain', function () {
    $channelManager = Double::for(ChannelManager::class);
    $this->app->instance(ChannelManager::class, $channelManager);

    $channel = new Channel('test-channel');

    $this->channelConnectionManager->expects('add')->with($this->connection, []);
    $this->channelConnectionManager->expects('remove')->with($this->connection);
    $this->channelConnectionManager->expects('isEmpty')->returns(true);
    $channelManager->expects('for')->returns($channelManager);
    $channelManager->expects('remove')->with($channel);

    $channel->subscribe($this->connection);
    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->allows('add');

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});

it('does not broadcast to the connection sending the message', function () {
    $channel = new Channel('test-channel');

    $this->channelConnectionManager->allows('add');

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $channel->broadcast(['foo' => 'bar'], collect($connections)->first()->connection());

    collect($connections)->first()->assertNothingReceived();
    collect(array_slice($connections, -2))->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});
