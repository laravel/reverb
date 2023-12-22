<?php

use Laravel\Reverb\Protocols\Pusher\Channels\CacheChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\Channel;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelBroker;
use Laravel\Reverb\Protocols\Pusher\Channels\PresenceCacheChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\PresenceChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\PrivateCacheChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\PrivateChannel;

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
