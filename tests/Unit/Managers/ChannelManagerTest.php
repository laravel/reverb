<?php

use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->channelManager = $this->app->make(ChannelManager::class)
        ->for($this->connection->app());
    $this->channel = $this->channelManager->find('test-channel-0');
    $this->connectionManager = $this->app->make(ConnectionManagerInterface::class)
        ->for($this->connection->app());
});

it('can subscribe to a channel', function () {
    collect(connections(5))
        ->each(fn ($connection) => $this->channel->subscribe($connection->connection()));

    expect($this->channel->connections())->toHaveCount(5);
});

it('can unsubscribe from a channel', function () {
    $connections = collect(connections(5))
        ->each(fn ($connection) => $this->channel->subscribe($connection->connection()));

    $this->channel->unsubscribe($connections->first()->connection());

    expect($this->channel->connections())->toHaveCount(4);
});

it('can get all channels', function () {
    $channels = collect(['test-channel-1', 'test-channel-2', 'test-channel-3']);

    $channels->each(fn ($channel) => $this->channelManager->find($channel)->subscribe($this->connection));

    $this->channelManager->all()->values()->each(function ($channel, $index) {
        expect($channel->name())->toBe('test-channel-'.($index));
    });
    expect($this->channelManager->all())->toHaveCount(4);
});

it('can get all connections subscribed to a channel', function () {
    $connections = collect(connections(5))
        ->each(fn ($connection) => $this->channel->subscribe($connection->connection()));

    $connections->each(fn ($connection) => expect($connection->identifier())
        ->toBeIn(array_keys($this->channel->connections())));
});

it('can unsubscribe a connection for all channels', function () {
    $channels = collect(['test-channel-0', 'test-channel-1', 'test-channel-2']);

    $channels->each(fn ($channel) => $this->channelManager->find($channel)->subscribe($this->connection));

    collect($this->channelManager->all())->each(fn ($channel) => expect($channel->connections())->toHaveCount(1));

    $this->channelManager->unsubscribeFromAll($this->connection);

    collect($this->channelManager->all())->each(fn ($channel) => expect($channel->connections())->toHaveCount(0));
});

it('can get the data for a connection subscribed to a channel', function () {
    collect(connections(5))->each(fn ($connection) => $this->channel->subscribe(
        $connection->connection(),
        data: json_encode(['name' => 'Joe'])
    ));

    collect($this->channel->connections())->each(function ($connection) {
        expect($connection->data())->toBe(['name' => 'Joe']);
    });
});

it('can get all connections for all channels', function () {
    $connections = connections(12);

    $channelOne = $this->channelManager->find('test-channel-0');
    $channelTwo = $this->channelManager->find('test-channel-1');
    $channelThree = $this->channelManager->find('test-channel-2');

    $connections = collect($connections)->split(3);

    $connections->first()->each(function ($connection) use ($channelOne, $channelTwo, $channelThree) {
        $channelOne->subscribe($connection->connection());
        $channelTwo->subscribe($connection->connection());
        $channelThree->subscribe($connection->connection());
    });

    $connections->get(1)->each(function ($connection) use ($channelTwo, $channelThree) {
        $channelTwo->subscribe($connection->connection());

        $channelThree->subscribe($connection->connection());
    });

    $connections->last()->each(function ($connection) use ($channelThree) {
        $channelThree->subscribe($connection->connection());
    });

    expect($channelOne->connections())->toHaveCount(4);
    expect($channelTwo->connections())->toHaveCount(8);
    expect($channelThree->connections())->toHaveCount(12);
});
