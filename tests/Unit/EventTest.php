<?php

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;

it('can publish an event when enabled', function () {
    $app = app(ApplicationProvider::class)->findByKey('reverb-key');
    app(ServerProviderManager::class)->withPublishing();
    $pubSub = Mockery::mock(PubSubProvider::class);
    $pubSub->shouldReceive('publish')->once()
        ->with(['type' => 'message', 'application' => serialize($app), 'payload' => ['channel' => 'test-channel']]);

    $this->app->instance(PubSubProvider::class, $pubSub);

    EventDispatcher::dispatch($app, ['channel' => 'test-channel']);
});

it('can broadcast an event directly when publishing disabled', function () {
    $channelConnectionManager = Mockery::mock(ChannelConnectionManager::class);
    $channelConnectionManager->shouldReceive('for')
        ->andReturn($channelConnectionManager);
    $channelConnectionManager->shouldReceive('all')->once()
        ->andReturn([]);

    $this->app->instance(ChannelConnectionManager::class, $channelConnectionManager);

    channels()->findOrCreate('test-channel');

    EventDispatcher::dispatch(app(ApplicationProvider::class)->findByKey('reverb-key'), ['channel' => 'test-channel']);
});

it('can broadcast an event for multiple channels', function () {
    $channelConnectionManager = Mockery::mock(ChannelConnectionManager::class);
    $channelConnectionManager->shouldReceive('for')
        ->andReturn($channelConnectionManager);
    $channelConnectionManager->shouldReceive('all')->twice()
        ->andReturn([]);

    $this->app->instance(ChannelConnectionManager::class, $channelConnectionManager);

    channels()->findOrCreate('test-channel-one');
    channels()->findOrCreate('test-channel-two');

    EventDispatcher::dispatch(app(ApplicationProvider::class)->findByKey('reverb-key'), ['channels' => ['test-channel-one', 'test-channel-two']]);
});
