<?php

use Laravel\Reverb\Protocols\Pusher\Channels\PresenceChannel;
use Laravel\Reverb\Protocols\Pusher\Channels\PrivateChannel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Mockery::spy(ChannelConnectionManager::class);
    $this->channelConnectionManager->shouldReceive('for')
        ->andReturn($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PrivateChannel('private-test-channel');

    $this->channelConnectionManager->shouldReceive('add')
        ->once()
        ->with($this->connection, []);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'private-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PrivateChannel('private-test-channel');

    $this->channelConnectionManager->shouldReceive('remove')
        ->once()
        ->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PrivateChannel('test-channel');

    $this->channelConnectionManager->shouldReceive('add');

    $this->channelConnectionManager->shouldReceive('all')
        ->once()
        ->andReturn($connections = factory(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PrivateChannel('private-test-channel');

    $this->channelConnectionManager->shouldNotReceive('subscribe');

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('fails to subscribe to a private channel with no auth token', function () {
    $channel = new PrivateChannel('private-test-channel');

    $channel->subscribe($this->connection, null);
})->throws(ConnectionUnauthorized::class);

it('fails to subscribe to a presence channel with no auth token', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $channel->subscribe($this->connection, null);
})->throws(ConnectionUnauthorized::class);
