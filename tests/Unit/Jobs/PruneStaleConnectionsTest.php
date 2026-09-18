<?php

use JMac\Testing\Double;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

beforeEach(function () {
    $this->channelManager = Double::for(ChannelManager::class);
    $this->channelManager->expects('for')->times(6)->returns($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('cleans up stale connections', function () {
    $connections = factory(5);
    $channel = ChannelBroker::create('test-channel');

    $this->channelManager->expects('connections')->returns($connections);
    $this->channelManager->allows('unsubscribeFromAll');

    collect($connections)->each(function ($connection) use ($channel) {
        $channel->subscribe($connection->connection());
        $connection->setLastSeenAt(time() - 60 * 10);
        $connection->setHasBeenPinged();
    });

    (new PruneStaleConnections)->handle($this->channelManager);

    collect($connections)->each(
        fn ($connection) => $this->channelManager->received('unsubscribeFromAll')->with($connection->connection())
    );
});
