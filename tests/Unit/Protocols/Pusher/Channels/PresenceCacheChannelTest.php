<?php

use JMac\Testing\Double;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Channels\PresenceCacheChannel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Double::for(ChannelConnectionManager::class);
    $this->channelConnectionManager->allows('for')->returns($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $this->channelConnectionManager->expects('add');

    $this->channelConnectionManager->allows('connections')->returns([]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-cache-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $this->channelConnectionManager->expects('remove')->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $this->channelConnectionManager->allows('subscribe');

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $this->channelConnectionManager->expects('subscribe')->never();

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('can return data stored on the connection', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $connections = [
        collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first(),
        collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 2]))->first(),
    ];

    $this->channelConnectionManager->expects('all')->returns($connections);

    expect($channel->data($this->connection->app()))->toBe([
        'presence' => [
            'count' => 2,
            'ids' => [1, 2],
            'hash' => [
                1 => ['name' => 'Joe'],
                2 => ['name' => 'Joe'],
            ],
        ],
    ]);
});

it('sends notification of subscription', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $this->channelConnectionManager->expects('add')->with($this->connection, []);

    $this->channelConnectionManager->allows('all')->returns($connections = factory(3));

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-cache-test-channel'));

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_added',
        'data' => '{}',
        'channel' => 'presence-cache-test-channel',
    ]));
});

it('sends notification of subscription with data', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');
    $data = json_encode(['name' => 'Joe']);

    $this->channelConnectionManager->expects('add')->with($this->connection, ['name' => 'Joe']);

    $this->channelConnectionManager->allows('all')->returns($connections = factory(3));

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-cache-test-channel',
            $data
        ),
        $data
    );

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_added',
        'data' => json_encode(['name' => 'Joe']),
        'channel' => 'presence-cache-test-channel',
    ]));
});

it('sends notification of an unsubscribe', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');
    $data = json_encode(['user_info' => ['name' => 'Joe'], 'user_id' => 1]);

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-cache-test-channel',
            $data
        ),
        $data
    );

    $this->channelConnectionManager->allows('find')->returns(new ChannelConnection($this->connection, ['user_info' => ['name' => 'Joe'], 'user_id' => 1]));

    $this->channelConnectionManager->allows('all')->returns($connections = factory(3));

    $this->channelConnectionManager->expects('remove')->with($this->connection);

    $channel->unsubscribe($this->connection);

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_removed',
        'data' => json_encode(['user_id' => 1]),
        'channel' => 'presence-cache-test-channel',
    ]));
});

it('receives no data when no previous event triggered', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    $this->channelConnectionManager->expects('add')->with($this->connection, []);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-cache-test-channel'));

    $this->connection->assertNothingReceived();
});

it('stores last triggered event', function () {
    $channel = new PresenceCacheChannel('presence-cache-test-channel');

    expect($channel->hasCachedPayload())->toBeFalse();

    $channel->broadcast(['foo' => 'bar']);

    expect($channel->hasCachedPayload())->toBeTrue();
    expect($channel->cachedPayload())->toEqual(['foo' => 'bar']);
});
