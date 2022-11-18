<?php

use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Channels\PresenceChannel;
use Laravel\Reverb\Channels\PrivateChannel;

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
