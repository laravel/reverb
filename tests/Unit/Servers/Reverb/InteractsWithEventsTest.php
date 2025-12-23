<?php

namespace Tests\Unit\Servers\Reverb;

use Laravel\Reverb\Events\ChannelCreated;
use Laravel\Reverb\Events\ConnectionPruned;
use Laravel\Reverb\Servers\Reverb\ReverbServerProvider;
use Mockery;

beforeEach(function () {
    $this->provider = new ReverbServerProvider($this->app, [
        'scaling' => [
            'enabled' => false,
            'channel' => 'reverb',
            'server' => [],
        ],
    ]);
});

it('can register a callback for channel created event', function () {
    $called = false;

    $this->provider->onChannelCreated('chat.*', function ($event) use (&$called) {
        $called = true;
    });

    $this->app['events']->dispatch(
        new ChannelCreated(mockChannel('chat.general'))
    );

    expect($called)->toBeTrue();
});

it('filters channels correctly using wildcards', function () {
    $called = false;

    $this->provider->onChannelCreated('orders.*', function () use (&$called) {
        $called = true;
    });

    $this->app['events']->dispatch(new ChannelCreated(mockChannel('chat.1')));
    expect($called)->toBeFalse();

    $this->app['events']->dispatch(new ChannelCreated(mockChannel('orders.1')));
    expect($called)->toBeTrue();
});

it('can register listeners without specifying a channel', function () {
    $called = false;

    $this->provider->onChannelCreated(function () use (&$called) {
        $called = true;
    });

    $this->app['events']->dispatch(new ChannelCreated(mockChannel('any.channel')));

    expect($called)->toBeTrue();
});

it('resolves an invokable class listener from the container', function () {
    $containerCalled = false;
    $listenerClass = 'ReverbFakeListener';

    $this->app->instance($listenerClass, new class($containerCalled) {
        public function __construct(protected &$containerCalled) {}
        public function __invoke($event) { $this->containerCalled = true; }
    });
    
    $this->provider->onChannelCreated('*', $listenerClass);

    $this->app['events']->dispatch(
        new ChannelCreated(mockChannel('any.channel'))
    );

    expect($containerCalled)->toBeTrue();
});

it('handles global events without channel filtering', function () {
    $called = false;

    $this->provider->onConnectionPruned(function () use (&$called) {
        $called = true;
    });

    $this->app['events']->dispatch(new ConnectionPruned(createFakeConnection()));

    expect($called)->toBeTrue();
});