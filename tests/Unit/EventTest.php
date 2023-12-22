<?php

use Clue\React\Redis\Client;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\ServerManager;

it('can publish an event when enabled', function () {
    $app = app(ApplicationProvider::class)->findByKey('pusher-key');
    app(ServerManager::class)->withPublishing();
    $redis = Mockery::mock(Client::class);
    $redis->shouldReceive('publish')->once()
        ->with('reverb', json_encode(['application' => serialize($app), 'payload' => ['channel' => 'test-channel']]));

    $this->app->bind(Client::class, fn () => $redis);

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

    EventDispatcher::dispatch(app(ApplicationProvider::class)->findByKey('pusher-key'), ['channel' => 'test-channel']);
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

    EventDispatcher::dispatch(app(ApplicationProvider::class)->findByKey('pusher-key'), ['channels' => ['test-channel-one', 'test-channel-two']]);
});
