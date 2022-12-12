<?php

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager as ConnectionManagerInterface;
use Laravel\Reverb\Managers\ChannelManager as Manager;
use Laravel\Reverb\Managers\ConnectionManager;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection;
    $this->channel = ChannelBroker::create('test-channel');
    $this->channelManager = $this->app->make(ChannelManager::class)
        ->for($this->connection->app());
    $this->connectionManager = $this->app->make(ConnectionManagerInterface::class)
        ->for($this->connection->app());
});

it('can subscribe to a channel', function () {
    connections(5)
        ->each(fn ($connection) => $this->channelManager->subscribe($this->channel, $connection));

    expect(
        $this->channelManager->connectionKeys($this->channel)
    )->toHaveCount(5);
});

it('can unsubscribe from a channel', function () {
    $connections = connections(5)
        ->each(fn ($connection) => $this->channelManager->subscribe($this->channel, $connection));

    $this->channelManager->unsubscribe($this->channel, $connections->first());

    expect($this->channelManager->connectionKeys($this->channel))->toHaveCount(4);
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
    $connections = connections(5)
        ->each(fn ($connection) => $this->channelManager->subscribe($this->channel, $connection));

    $connections->each(fn ($connection) => expect($connection->identifier())
        ->toBeIn($this->channelManager->connectionKeys($this->channel)->keys()));
});

it('can get all hydrated connections subscribed to a channel', function () {
    $connections = connections(5)
        ->each(fn ($connection) => $this->channelManager->subscribe($this->channel, $connection));

    $this->connectionManager
        ->sync($connections->mapWithKeys(
            fn ($connection) => [$connection->identifier() => $connection]
        ));

    $hydratedConnections = $this->channelManager->connections($this->channel);

    $this->expect($hydratedConnections)->toHaveCount(5);
    $hydratedConnections->each(function ($connection) {
        expect($connection)->toBeInstanceOf(Connection::class);
    });
});

it('can get all hydrated serialized connections subscribed to a channel', function () {
    $connections = connections(5, true)
        ->each(fn ($connection) => $this->channelManager->subscribe($this->channel, $connection));

    $this->connectionManager
        ->sync($connections->mapWithKeys(
            fn ($connection) => [$connection->identifier() => serialize($connection)]
        ));

    $hydratedConnections = $this->channelManager->connections($this->channel);

    $this->expect($hydratedConnections)->toHaveCount(5);
    $hydratedConnections->each(function ($connection) {
        expect(Connection::hydrate($connection))->toBeInstanceOf(Connection::class);
    });
});

it('only valid hydrated connections are returned', function () {
    $connections = connections(10);

    $this->connectionManager
        ->sync($connections->mapWithKeys(
            fn ($connection) => [$connection->identifier() => $connection]
        ));

    $connections->take(5)->each(fn ($connection) => $this->channelManager->subscribe($this->channel, $connection));

    $hydratedConnections = $this->channelManager->connections($this->channel);
    $allConnections = $this->connectionManager->all();

    $this->expect($hydratedConnections)->toHaveCount(5);
    $this->expect($allConnections)->toHaveCount(10);
    $hydratedConnections->each(function ($connection, $index) use ($allConnections) {
        expect($connection->identifier())->toBe($index);
        expect($index)->toBeIn($allConnections->take(5)->keys());
    });
});

it('can unsubscribe a connection for all channels', function () {
    $channels = collect(['test-channel-1', 'test-channel-2', 'test-channel-3'])
        ->map(fn ($name) => ChannelBroker::create($name));

    $channels->each(fn ($channel) => $this->channelManager->subscribe(
        $channel,
        $this->connection
    ));

    $channels->each(fn ($channel) => expect($this->channelManager->connectionKeys($channel))->toHaveCount(1));

    $this->channelManager->unsubscribeFromAll($this->connection);

    $channels->each(fn ($channel) => expect($this->channelManager->connectionKeys($channel))->toHaveCount(0));
});

it('can use a custom cache prefix', function () {
    $channelManager = (new Manager(
        Cache::store('array'),
        App::make(ConnectionManager::class),
        'reverb-test'
    ))->for($this->connection->app());

    $channelManager->subscribe(
        $this->channel,
        $connection = new Connection
    );

    expect(Cache::store('array')->get("reverb-test:{$connection->app()->id()}:channels"))
        ->toHaveCount(1);
});

it('can get the data for a connection subscribed to a channel', function () {
    connections(5)->each(fn ($connection) => $this->channelManager->subscribe(
        $this->channel,
        $connection,
        ['name' => 'Joe']
    ));

    $this->channelManager->connectionKeys($this->channel)->values()->each(function ($data) {
        expect($data)
            ->toBe(['name' => 'Joe']);
    });
});

it('can get all connections for all channels', function () {
    $connections = connections(12);

    $channelOne = ChannelBroker::create('test-channel-1');
    $channelTwo = ChannelBroker::create('test-channel-2');
    $channelThree = ChannelBroker::create('test-channel-3');

    $connections = $connections->split(3);

    $connections->first()->each(function ($connection) use ($channelOne, $channelTwo, $channelThree) {
        $this->channelManager->subscribe($channelOne, $connection);
        $this->channelManager->subscribe($channelTwo, $connection);
        $this->channelManager->subscribe($channelThree, $connection);
    });

    $connections->get(1)->each(function ($connection) use ($channelTwo, $channelThree) {
        $this->channelManager->subscribe($channelTwo, $connection);

        $this->channelManager->subscribe($channelThree, $connection);
    });

    $connections->last()->each(function ($connection) use ($channelThree) {
        $this->channelManager->subscribe($channelThree, $connection);
    });

    $this->assertCount(4, $this->channelManager->connectionKeys($channelOne));
    $this->assertCount(8, $this->channelManager->connectionKeys($channelTwo));
    $this->assertCount(12, $this->channelManager->connectionKeys($channelThree));
});
