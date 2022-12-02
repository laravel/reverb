<?php

use Illuminate\Support\Facades\Cache;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Managers\ChannelManager as Manager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->channel = ChannelBroker::create('test-channel');
    $this->channelManager = $this->app->make(ChannelManager::class)
        ->for($this->connection->app());
});

it('can subscribe to a channel', function () {
    connections(5)->each(fn ($connection) => $this->channelManager->subscribe(
        $this->channel,
        $connection['connection'],
        $connection['data'])
    );

    expect(
        $this->channelManager->connections($this->channel)
    )->toHaveCount(5);
});

it('can unsubscribe from a channel', function () {
    $connections = connections(5)->each(fn ($connection) => $this->channelManager->subscribe(
        $this->channel,
        $connection['connection'],
        $connection['data'])
    );

    $this->channelManager->unsubscribe($this->channel, $connections->first()['connection']);

    expect($this->channelManager->connections($this->channel))->toHaveCount(4);
});

it('can get all channels', function () {
    $channels = collect(['test-channel-1', 'test-channel-2', 'test-channel-3'])
        ->map(fn ($name) => ChannelBroker::create($name));

    $channels->each(fn ($channel) => $this->channelManager->subscribe(
        $channel,
        $this->connection
    ));

    $this->channelManager->all()->values()->each(function ($channel, $index) {
        expect($channel->name())->toBe('test-channel-'.($index + 1));
    });
    expect($this->channelManager->all())->toHaveCount(3);
});

it('can get all connections subscribed to a channel', function () {
    $connections = connections(5)->each(fn ($connection) => $this->channelManager->subscribe(
        $this->channel,
        $connection['connection'],
        $connection['data'])
    );

    $this->channelManager->connections($this->channel)->each(function ($connection, $index) {
        expect($connection['connection']->identifier())
            ->toBe($index);
    });
});

it('can unsubscribe a connection for all channels', function () {
    $channels = collect(['test-channel-1', 'test-channel-2', 'test-channel-3'])
        ->map(fn ($name) => ChannelBroker::create($name));

    $channels->each(fn ($channel) => $this->channelManager->subscribe(
        $channel,
        $this->connection
    ));

    $channels->each(fn ($channel) => expect($this->channelManager->connections($channel))->toHaveCount(1));

    $this->channelManager->unsubscribeFromAll($this->connection);

    $channels->each(fn ($channel) => expect($this->channelManager->connections($channel))->toHaveCount(0));
});

it('can use a custom cache prefix', function () {
    $channelManager = (new Manager(
        Cache::store('array'),
        'reverb-test'
    ))->for($this->connection->app());

    $channelManager->subscribe(
        $this->channel,
        $connection = new Connection
    );

    expect(Cache::get("reverb-test:channels:{$connection->app()->id()}"))
        ->toHaveCount(1);
});

it('can get the data for a connection subscribed to a channel', function () {
    connections(5, ['name' => 'Joe'])->each(fn ($connection) => $this->channelManager->subscribe(
        $this->channel,
        $connection['connection'],
        $connection['data'])
    );

    $this->channelManager->connections($this->channel)->values()->each(function ($connection, $index) {
        expect($connection['data'])
            ->toBe(['name' => 'Joe', 'user_id' => $index + 1]);
    });
});

it('can get all connections for all channels', function () {
    $connections = connections(12);

    $channelOne = ChannelBroker::create('test-channel-1');
    $channelTwo = ChannelBroker::create('test-channel-2');
    $channelThree = ChannelBroker::create('test-channel-3');

    $connections = $connections->split(3);

    $connections->first()->each(function ($connection) use ($channelOne, $channelTwo, $channelThree) {
        $this->channelManager->subscribe(
            $channelOne,
            $connection['connection'],
            $connection['data']
        );

        $this->channelManager->subscribe(
            $channelTwo,
            $connection['connection'],
            $connection['data']
        );

        $this->channelManager->subscribe(
            $channelThree,
            $connection['connection'],
            $connection['data']
        );
    });

    $connections->get(1)->each(function ($connection) use ($channelTwo, $channelThree) {
        $this->channelManager->subscribe(
            $channelTwo,
            $connection['connection'],
            $connection['data']
        );

        $this->channelManager->subscribe(
            $channelThree,
            $connection['connection'],
            $connection['data']
        );
    });

    $connections->last()->each(function ($connection) use ($channelThree) {
        $this->channelManager->subscribe(
            $channelThree,
            $connection['connection'],
            $connection['data']
        );
    });

    $this->assertCount(4, $this->channelManager->connections($channelOne));
    $this->assertCount(8, $this->channelManager->connections($channelTwo));
    $this->assertCount(12, $this->channelManager->connections($channelThree));
    $this->assertCount(12, $this->channelManager->allConnections());
});
