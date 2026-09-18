<?php

use JMac\Testing\Double;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\EventHandler as PusherEventHandler;
use Laravel\Reverb\Protocols\Pusher\MetricsHandler;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Tests\FakeConnection;

use function React\Promise\reject;

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

it('falls back to local members when the gather fails', function () {
    $this->app->instance(PubSubProvider::class, Double::for(PubSubProvider::class));
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(reject(new Exception('Unable to gather metrics.')));
    $this->app->instance(MetricsHandler::class, $metrics);

    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $this->pusher->handle(
        $this->connection,
        'pusher:subscribe',
        [
            'channel' => 'presence-test-channel',
            'auth' => validAuth($this->connection->id(), 'presence-test-channel', $data),
            'channel_data' => $data,
        ]
    );

    $this->connection->assertReceived([
        'event' => 'pusher_internal:subscription_succeeded',
        'data' => json_encode(['presence' => ['count' => 1, 'ids' => [1], 'hash' => [1 => ['name' => 'Joe']]]]),
        'channel' => 'presence-test-channel',
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
