<?php

use Clue\React\Redis\Client;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Event;
use Laravel\Reverb\Managers\Connections;

it('can broadcast a pubsub event when enabled', function () {
    $app = Application::findByKey('pusher-key');
    Config::set('reverb.pubsub.enabled', true);
    $redis = Mockery::mock(Client::class);
    $redis->shouldReceive('publish')->once()
        ->with('reverb', json_encode(['application' => serialize($app), 'payload' => ['channel' => 'test-channel']]));

    $this->app->bind(Client::class, fn () => $redis);

    Event::dispatch($app, ['channel' => 'test-channel']);
});

it('can broadcast an event directly when pubsub disabled', function () {
    Config::set('reverb.pubsub.enabled', false);
    $channelManager = Mockery::mock(ChannelManager::class);
    $channelManager->shouldReceive('for')
        ->andReturn($channelManager);
    $channelManager->shouldReceive('connections')->once()
        ->andReturn(Connections::make());

    $this->app->bind(ChannelManager::class, fn () => $channelManager);

    Event::dispatch(Application::findByKey('pusher-key'), ['channel' => 'test-channel']);
});
