<?php

use Laravel\Reverb\Protocols\Pusher\Channels\CacheChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->channelConnectionManager->shouldReceive('for')
        ->andReturn($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('receives no data when no previous event triggered', function () {
    $channel = ChannelBroker::create('cache-test-channel');
    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection);

    $this->connection->assertNothingReceived();
});

it('stores last triggered event', function () {
    $channel = new CacheChannel('cache-test-channel');

    expect($channel->hasCachedPayload())->toBeFalse();

    $channel->broadcast(['foo' => 'bar']);

    expect($channel->hasCachedPayload())->toBeTrue();
    expect($channel->cachedPayload())->toEqual(['foo' => 'bar']);
});
