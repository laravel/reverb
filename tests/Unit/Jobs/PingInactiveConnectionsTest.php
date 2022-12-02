<?php

use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Jobs\PingInactiveConnections;

beforeEach(function () {
    $this->channelManager = Mockery::spy(ChannelManager::class);
    $this->channelManager->shouldReceive('for')
        ->andReturn($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('pings inactive connections', function () {
    $connections = connections(5);
    $channel = ChannelBroker::create('test-channel');

    $this->channelManager->shouldReceive('allConnections')
        ->once()
        ->andReturn($connections);

    $connections = $connections->map(fn ($connection) => $connection['connection'])
        ->each(function ($connection) use ($channel) {
            $channel->subscribe($connection);
            $connection->setLastSeenAt(now()->subMinutes(10));
        });

    (new PingInactiveConnections)->handle($this->channelManager);

    $connections->each(function ($connection) {
        $connection->assertSent([
            'event' => 'pusher:ping',
        ]);
        $connection->assertHasBeenPinged();
    });
});
