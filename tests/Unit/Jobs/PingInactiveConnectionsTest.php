<?php

use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Managers\ConnectionManager;

beforeEach(function () {
    $this->connectionManager = Mockery::spy(ConnectionManager::class);
    $this->connectionManager->shouldReceive('for')
        ->andReturn($this->connectionManager);
    $this->app->singleton(ConnectionManager::class, fn () => $this->connectionManager);
});

it('pings inactive connections', function () {
    $connections = connections(5);
    $channel = ChannelBroker::create('test-channel');

    $this->connectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections);

    $connections = $connections->each(function ($connection) use ($channel) {
        $channel->subscribe($connection);
        $connection->setLastSeenAt(now()->subMinutes(10));
    });

    (new PingInactiveConnections)->handle($this->connectionManager);

    $connections->each(function ($connection) {
        $connection->assertSent([
            'event' => 'pusher:ping',
        ]);
        $connection->assertHasBeenPinged();
    });
});
