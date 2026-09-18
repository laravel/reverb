<?php

use JMac\Testing\Double;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;

it('can publish an event when enabled', function () {
    $app = app(ApplicationProvider::class)->findByKey('reverb-key');
    app(ServerProviderManager::class)->withPublishing();
    $pubSub = Double::for(PubSubProvider::class);
    $pubSub->expects('publish')->with(['type' => 'message', 'application' => serialize($app), 'payload' => ['channel' => 'test-channel']]);

    $this->app->instance(PubSubProvider::class, $pubSub);

    EventDispatcher::dispatch($app, ['channel' => 'test-channel']);
});

it('can broadcast an event directly when publishing disabled', function () {
    $channelConnectionManager = Double::for(ChannelConnectionManager::class);
    $channelConnectionManager->expects('for')->returns($channelConnectionManager);
    $channelConnectionManager->expects('all')->returns([]);

    $this->app->instance(ChannelConnectionManager::class, $channelConnectionManager);

    channels()->findOrCreate('test-channel');

    EventDispatcher::dispatch(app(ApplicationProvider::class)->findByKey('reverb-key'), ['channel' => 'test-channel']);
});

it('can broadcast an event for multiple channels', function () {
    $channelConnectionManager = Double::for(ChannelConnectionManager::class);
    $channelConnectionManager->expects('for')->times(2)->returns($channelConnectionManager);
    $channelConnectionManager->expects('all')->times(2)->returns([]);

    $this->app->instance(ChannelConnectionManager::class, $channelConnectionManager);

    channels()->findOrCreate('test-channel-one');
    channels()->findOrCreate('test-channel-two');

    EventDispatcher::dispatch(app(ApplicationProvider::class)->findByKey('reverb-key'), ['channels' => ['test-channel-one', 'test-channel-two']]);
});
