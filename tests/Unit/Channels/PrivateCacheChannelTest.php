<?php

use Laravel\Reverb\Channels\PrivateCacheChannel;
use Laravel\Reverb\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Tests\Connection;

beforeEach(function () {
    $this->connection = new Connection();
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PrivateCacheChannel('private-cache-test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'private-cache-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PrivateCacheChannel('private-cache-test-channel');

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PrivateCacheChannel('test-channel');

    $this->channelConnectionManager->shouldReceive('add');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = connections(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertSent(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PrivateCacheChannel('presence-test-channel');

    $this->channelConnectionManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('receives no data when no previous event triggered', function () {
    $channel = new PrivateCacheChannel('private-cache-test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'private-cache-test-channel'));

    $this->connection->assertNothingSent();
});

it('stores last triggered event', function () {
    $channel = new PrivateCacheChannel('presence-test-channel');

    expect($channel->hasCachedPayload())->toBeFalse();

    $channel->broadcast(['foo' => 'bar']);

    expect($channel->hasCachedPayload())->toBeTrue();
    expect($channel->cachedPayload())->toEqual(['foo' => 'bar']);
});
