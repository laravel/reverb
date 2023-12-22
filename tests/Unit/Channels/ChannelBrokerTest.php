<?php

use Laravel\Reverb\Pusher\Channels\CacheChannel;
use Laravel\Reverb\Pusher\Channels\Channel;
use Laravel\Reverb\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Pusher\Channels\PresenceCacheChannel;
use Laravel\Reverb\Pusher\Channels\PresenceChannel;
use Laravel\Reverb\Pusher\Channels\PrivateCacheChannel;
use Laravel\Reverb\Pusher\Channels\PrivateChannel;

it('can return a channel instance', function () {
    expect(ChannelBroker::create('foo'))
        ->toBeInstanceOf(Channel::class);
});

it('can return a private channel instance', function () {
    expect(ChannelBroker::create('private-foo'))
        ->toBeInstanceOf(PrivateChannel::class);
});

it('can return a presence channel instance', function () {
    expect(ChannelBroker::create('presence-foo'))
        ->toBeInstanceOf(PresenceChannel::class);
});

it('can return a cache channel instance', function () {
    expect(ChannelBroker::create('cache-foo'))
        ->toBeInstanceOf(CacheChannel::class);
});

it('can return a private cache channel instance', function () {
    expect(ChannelBroker::create('private-cache-foo'))
        ->toBeInstanceOf(PrivateCacheChannel::class);
});

it('can return a presence cache channel instance', function () {
    expect(ChannelBroker::create('presence-cache-foo'))
        ->toBeInstanceOf(PresenceCacheChannel::class);
});
