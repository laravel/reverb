<?php

use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Jobs\PruneStaleConnections;

beforeEach(function () {
    $this->connectionManager = Mockery::spy(ConnectionManager::class);
    $this->connectionManager->shouldReceive('for')
        ->andReturn($this->connectionManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);

    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->channelManager->shouldReceive('for')
        ->andReturn($this->channelManager);
    $this->app->singleton(ConnectionManager::class, fn () => $this->connectionManager);
});

it('cleans up stale connections', function () {
    $connections = connections(5);
    $channel = ChannelBroker::create('test-channel');

    $this->connectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections);

    $connections->each(function ($connection) use ($channel) {
        $channel->subscribe($connection);
        $connection->setLastSeenAt(now()->subMinutes(10));
        $connection->setHasBeenPinged();

        $this->channelManager->shouldReceive('unsubscribeFromAll')
                ->once()
                ->with($connection);
    });

    (new PruneStaleConnections)->handle(
        $this->connectionManager,
        $this->channelManager
    );
});
