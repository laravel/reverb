<?php

use Clue\React\Redis\Client;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Event;
use Laravel\Reverb\Managers\Connections;

it('can publish an event when enabled', function () {
    $app = app(ApplicationProvider::class)->findByKey('pusher-key');
    App::make(ServerProvider::class)->withPublishing();
    $redis = Mockery::mock(Client::class);
    $redis->shouldReceive('publish')->once()
        ->with('reverb', json_encode(['application' => serialize($app), 'payload' => ['channel' => 'test-channel']]));

    $this->app->bind(Client::class, fn () => $redis);

    Event::dispatch($app, ['channel' => 'test-channel']);
});

it('can broadcast an event directly when publishing disabled', function () {
    $channelManager = Mockery::mock(ChannelManager::class);
    $channelManager->shouldReceive('for')
        ->andReturn($channelManager);
    $channelManager->shouldReceive('connections')->once()
        ->andReturn(Connections::make());

    $this->app->bind(ChannelManager::class, fn () => $channelManager);

    Event::dispatch(app(ApplicationProvider::class)->findByKey('pusher-key'), ['channel' => 'test-channel']);
});
