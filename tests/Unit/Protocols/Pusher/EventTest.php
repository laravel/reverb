<?php

use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\EventHandler as PusherEventHandler;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->pusher = new PusherEventHandler(app(ChannelManager::class));
});

it('can send an acknowledgement', function () {
    $this->pusher->handle(
        $this->connection,
        'pusher:connection_established'
    );

    $this->connection->assertReceived([
        'event' => 'pusher:connection_established',
        'data' => json_encode([
            'socket_id' => $this->connection->id(),
            'activity_timeout' => 30,
        ]),
    ]);
});

it('can subscribe to a channel', function () {
    $this->pusher->handle(
        $this->connection,
        'pusher:subscribe',
        ['channel' => 'test-channel']
    );

    $this->connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
        'channel' => 'test-channel',
    ]);
});

it('can subscribe to an empty channel', function () {
    $this->pusher->handle(
        $this->connection,
        'pusher:subscribe',
        ['channel' => '']
    );

    $this->connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => '{}',
    ]);
});

it('can unsubscribe from a channel', function () {
    $this->pusher->handle(
        $this->connection,
        'pusher:unsubscribe',
        ['channel' => 'test-channel']
    );

    $this->connection->assertNothingReceived();
});

it('can respond to a ping', function () {
    $this->pusher->handle(
        $this->connection,
        'pusher:ping',
    );

    $this->connection->assertReceived([
        'event' => 'pusher:pong',
    ]);
});

it('can correctly format a payload', function () {
    $payload = $this->pusher->formatPayload(
        'foo',
        ['bar' => 'baz'],
        'test-channel',
    );

    expect($payload)->toBe(json_encode([
        'event' => 'pusher:foo',
        'data' => json_encode(['bar' => 'baz']),
        'channel' => 'test-channel',
    ]));

    $payload = $this->pusher->formatPayload('foo');

    expect($payload)->toBe(json_encode([
        'event' => 'pusher:foo',
    ]));
});

it('can correctly format an internal payload', function () {
    $payload = $this->pusher->formatInternalPayload(
        'foo',
        ['bar' => 'baz'],
        'test-channel',
    );

    expect($payload)->toBe(json_encode([
        'event' => 'pusher_internal:foo',
        'data' => json_encode(['bar' => 'baz']),
        'channel' => 'test-channel',
    ]));

    $payload = $this->pusher->formatInternalPayload('foo');

    expect($payload)->toBe(json_encode([
        'event' => 'pusher_internal:foo',
        'data' => '{}',
    ]));
});
