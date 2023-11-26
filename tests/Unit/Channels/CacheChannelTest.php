<?php

use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('receives no data when no previous event triggered', function () {
    $channel = ChannelBroker::create('cache-test-channel');
    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection);

    $this->connection->assertNothingSent();
});

it('receives last triggered event', function () {
    $channel = ChannelBroker::create('cache-test-channel');

    $channel->broadcast(['foo' => 'bar']);

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection);

    $this->connection->assertSent(['foo' => 'bar']);
});
