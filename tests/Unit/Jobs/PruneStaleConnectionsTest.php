<?php

use Illuminate\Support\Facades\Event;
use JMac\Testing\Double;
use Laravel\Reverb\Events\ConnectionPruned;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Protocols\Pusher\ConnectionRegistry;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;

beforeEach(function () {
    $this->channelManager = Double::for(ChannelManager::class);
    $this->channelManager->expects('for')->times(5)->returns($this->channelManager);
    $this->app->singleton(ChannelManager::class, fn () => $this->channelManager);
});

it('cleans up stale connections', function () {
    Event::fake([ConnectionPruned::class]);

    $connections = factory(5, ['user_id' => 1]);
    $channel = ChannelBroker::create('test-channel');

    $this->channelManager->allows('connections')->returns(collect($connections)->keyBy->id()->all());
    $this->channelManager->allows('unsubscribeFromAll');

    collect($connections)->each(function ($connection) use ($channel) {
        app(ConnectionRegistry::class)->add($connection->connection());
        $channel->subscribe($connection->connection());
        $connection->setLastSeenAt(time() - 60 * 10);
        $connection->setHasBeenPinged();
    });

    (new PruneStaleConnections)->handle($this->channelManager);

    collect($connections)->each(
        fn ($connection) => $this->channelManager->received('unsubscribeFromAll')->with($connection->connection())
    );

    expect(app(ConnectionRegistry::class)->for($connections[0]->app()))->toBeEmpty();

    Event::assertDispatchedTimes(ConnectionPruned::class, 5);
    Event::assertDispatched(ConnectionPruned::class, fn ($event) => $event->connection->data('user_id') === 1);
    Event::assertNotDispatched(ConnectionPruned::class, fn ($event) => $event->connection->data('user_id') !== 1);
});
