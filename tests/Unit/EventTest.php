<?php

use Clue\React\Redis\Client;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Event;

it('can broadcast a pubsub event when enabled', function () {
    Config::set('reverb.pubsub.enabled', true);
    $redis = Mockery::mock(Client::class);
    $redis->shouldReceive('publish')->once()
        ->with('reverb', json_encode(['channel' => 'test-channel']));

    $this->app->bind(Client::class, fn () => $redis);

    Event::dispatch(['channel' => 'test-channel']);
});

it('can broadcast an event directly when pubsub disabled', function () {
    Config::set('reverb.pubsub.enabled', false);
    $channelManager = Mockery::mock(ChannelManager::class);
    $channelManager->shouldReceive('connections')->once()
        ->andReturn(collect());

    $this->app->bind(ChannelManager::class, fn () => $channelManager);

    Event::dispatch(['channel' => 'test-channel']);
});
