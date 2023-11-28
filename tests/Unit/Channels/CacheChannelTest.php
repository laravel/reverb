<?php

use Laravel\Reverb\Channels\CacheChannel;
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

it('stores last triggered event', function () {
    $channel = new CacheChannel('cache-test-channel');

    expect($channel->hasCachedPayload())->toBeFalse();

    $channel->broadcast(['foo' => 'bar']);

    expect($channel->hasCachedPayload())->toBeTrue();
    expect($channel->cachedPayload())->toEqual(['foo' => 'bar']);
});
