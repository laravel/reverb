<?php

use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Jobs\PruneStaleConnections;

beforeEach(function () {
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->channelManager->shouldReceive('for')
        ->andReturn($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('cleans up stale connections', function () {
    $connections = connections(5);
    $channel = ChannelBroker::create('test-channel');

    $this->channelManager->shouldReceive('connections')
        ->once()
        ->andReturn(collect($connections));

    collect($connections)->each(function ($connection) use ($channel) {
        $channel->subscribe($connection->connection());
        $connection->setLastSeenAt(now()->subMinutes(10));
        $connection->setHasBeenPinged();

        $this->channelManager->shouldReceive('unsubscribeFromAll')
            ->once()
            ->with($connection->connection());
    });

    (new PruneStaleConnections)->handle($this->channelManager);
});
