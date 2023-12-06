<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Bus;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Servers\ApiGateway\Request;
use Laravel\Reverb\Servers\ApiGateway\Server;
use Laravel\Reverb\Tests\ApiGatewayTestCase;

uses(ApiGatewayTestCase::class);

beforeEach(function () {
    Bus::fake();
});

afterEach(function () {
    channelManager()->flush();
    connections()->flush();
});

it('can handle a new connection', function () {
    $this->connect();

    $this->assertCount(1, connections()->all());
});

it('can handle multiple new connections', function () {
    $this->connect();
    $this->connect('def-456');

    $this->assertCount(2, connections()->all());
});

it('can handle connections to different applications', function () {
    $this->connect();
    $this->connect('def-456', appKey: 'pusher-key-2');

    $connections = connections()->all();

    expect(Arr::first($connections)->identifier())->toBe('abc-123');
    expect(Arr::first($connections)->app()->id())->toBe('123456');
    expect(Arr::last($connections)->identifier())->toBe('def-456');
    expect(Arr::last($connections)->app()->id())->toBe('654321');
});

it('can subscribe to a channel', function () {
    $this->subscribe('test-channel');

    expect(connections()->all())->toHaveCount(1);

    expect(channelManager()->find('test-channel')->connections())->toHaveCount(1);

    $this->assertSent('abc-123', '{"event":"pusher_internal:subscription_succeeded","channel":"test-channel"}');
});

it('can subscribe to a private channel', function () {
    $this->subscribe('private-test-channel');

    $this->assertSent('abc-123', '{"event":"pusher_internal:subscription_succeeded","channel":"private-test-channel"}');
});

it('can subscribe to a presence channel', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User']];
    $this->subscribe('presence-test-channel', data: $data);

    $this->assertSent('abc-123', [
        'pusher_internal:subscription_succeeded',
        '"hash\":{\"1\":{\"name\":\"Test User\"}}',
    ]);
});

it('can notify other subscribers of a presence channel when a new member joins', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']];
    $this->subscribe('presence-test-channel', data: $data);

    $data = ['user_id' => 2, 'user_info' => ['name' => 'Test User 2']];
    $this->subscribe('presence-test-channel', data: $data, connectionId: 'def-456');
    $this->assertSent('abc-123', '{"event":"pusher_internal:member_added","data":{"user_id":2,"user_info":{"name":"Test User 2"}},"channel":"presence-test-channel"}');

    $data = ['user_id' => 3, 'user_info' => ['name' => 'Test User 3']];
    $this->subscribe('presence-test-channel', data: $data, connectionId: 'ghi-789');
    $this->assertSent('def-456', '{"event":"pusher_internal:member_added","data":{"user_id":3,"user_info":{"name":"Test User 3"}},"channel":"presence-test-channel"}');
});

it('can notify other subscribers of a presence channel when a member leaves', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']];
    $this->subscribe('presence-test-channel', data: $data);

    $data = ['user_id' => 2, 'user_info' => ['name' => 'Test User 2']];
    $this->subscribe('presence-test-channel', data: $data, connectionId: 'def-456');
    $this->assertSent('abc-123', '{"event":"pusher_internal:member_added","data":{"user_id":2,"user_info":{"name":"Test User 2"}},"channel":"presence-test-channel"}');

    $data = ['user_id' => 3, 'user_info' => ['name' => 'Test User 3']];
    $this->subscribe('presence-test-channel', data: $data, connectionId: 'ghi-789');
    $this->assertSent('def-456', '{"event":"pusher_internal:member_added","data":{"user_id":3,"user_info":{"name":"Test User 3"}},"channel":"presence-test-channel"}');

    expect(connections()->all())->toHaveCount(3);

    $this->disconnect('ghi-789');

    expect(connections()->all())->toHaveCount(2);

    $this->assertSent(
        message: '{"event":"pusher_internal:member_removed","data":{"user_id":3},"channel":"presence-test-channel"}',
        times: 2
    );
});

it('can receive a message broadcast from the server', function () {
    $this->subscribe('test-channel');
    $this->subscribe('test-channel', connectionId: 'def-456');
    $this->subscribe('test-channel', connectionId: 'ghi789');

    $this->post('apps/123456/events', [
        'name' => 'App\\Events\\TestEvent',
        'channel' => 'test-channel',
        'data' => ['foo' => 'bar'],
    ])->assertOk();

    $this->assertSent(message: '{"event":"App\\\\Events\\\\TestEvent","channel":"test-channel","data":{"foo":"bar"}}');
});

it('can handle an event', function () {
    $this->subscribe('presence-test-channel', data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    $this->post('apps/123456/events', [
        'name' => 'App\\Events\\TestEvent',
        'channel' => 'presence-test-channel',
        'data' => ['foo' => 'bar'],
    ])->assertOk();

    $this->assertSent('abc-123', message: '{"event":"App\\\\Events\\\\TestEvent","channel":"presence-test-channel","data":{"foo":"bar"}}');
});

it('can respond to a ping', function () {
    $this->send(['event' => 'pusher:ping']);

    $this->assertSent('abc-123', '{"event":"pusher:pong"}', 1);
});

it('it can ping inactive subscribers', function () {
    $this->connect();
    $this->subscribe('test-channel');
    $this->assertSent('abc-123', 'subscription_succeeded', 1);

    $connection = Arr::first(connections()->all());
    $connection->setLastSeenAt(time() - 60 * 10);
    connections()->connect($connection);

    (new PingInactiveConnections)->handle(
        channelManager()
    );

    $this->assertSent('abc-123', '{"event":"pusher:ping"}', 1);
});

it('it can disconnect inactive subscribers', function () {
    $this->subscribe('test-channel');

    $connection = Arr::first(connections()->all());
    $connection->setLastSeenAt(time() - 60 * 10);
    connections()->connect($connection);

    (new PingInactiveConnections)->handle(
        channelManager()
    );
    $this->assertSent('abc-123', '{"event":"pusher:ping"}');

    (new PruneStaleConnections)->handle(
        channelManager()
    );

    // expect(connections()->all())->toHaveCount(0);
    expect(channelManager()->find('test-channel')->connections())->toHaveCount(0);

    $this->assertSent('abc-123', '{"event":"pusher:error","data":"{\"code\":4201,\"message\":\"Pong reply not received in time\"}"}', 1);
});

it('can handle a client whisper', function () {
    $this->subscribe('test-channel');

    $this->subscribe('test-channel', connectionId: 'def-456');

    $this->send([
        'event' => 'client-start-typing',
        'channel' => 'test-channel',
        'data' => [
            'id' => 123,
            'name' => 'Joe Dixon',
        ],
    ], 'abc-123');

    $this->assertSent('def-456', '{"event":"client-start-typing","channel":"test-channel","data":{"id":123,"name":"Joe Dixon"}}', 1);
});

it('can subscribe a connection to multiple channels', function () {
    $this->subscribe('test-channel');
    $this->subscribe('test-channel-2');
    $this->subscribe('private-test-channel-3', data: ['foo' => 'bar']);
    $this->subscribe('presence-test-channel-4', data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    expect(connections()->all())->toHaveCount(1);
    expect(channelManager()->all())->toHaveCount(4);

    $connection = $this->managedConnection();

    collect(channelManager()->all())->each(function ($channel) use ($connection) {
        expect($channel->connections())->toHaveCount(1);
        expect(collect($channel->connections())->map(fn ($conn, $index) => (string) $index))->toContain($connection->identifier());
    });
});

it('can subscribe multiple connections to multiple channels', function () {
    $this->subscribe('test-channel');
    $this->subscribe('test-channel-2');
    $this->subscribe('private-test-channel-3', data: ['foo' => 'bar']);
    $this->subscribe('presence-test-channel-4', data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    $this->connect();
    $this->subscribe('test-channel', connectionId: 'def-456');
    $this->subscribe('private-test-channel-3', connectionId: 'def-456', data: ['foo' => 'bar']);

    expect(connections()->all())->toHaveCount(2);
    expect(channelManager()->all())->toHaveCount(4);

    expect(channelManager()->find('test-channel')->connections())->toHaveCount(2);
    expect(channelManager()->find('test-channel-2')->connections())->toHaveCount(1);
    expect(channelManager()->find('private-test-channel-3')->connections())->toHaveCount(2);
    expect(channelManager()->find('presence-test-channel-4')->connections())->toHaveCount(1);
});

it('fails to subscribe to a private channel with invalid auth signature', function () {
    $this->subscribe('private-test-channel', auth: 'invalid-signature');

    $this->assertSent('abc-123', '{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to subscribe to a presence channel with invalid auth signature', function () {
    $this->subscribe('presence-test-channel', auth: 'invalid-signature');

    $this->assertSent('abc-123', '{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to connect when an invalid application is provided', function () {
    resolve(Server::class)
        ->handle(Request::fromLambdaEvent(
            [
                'requestContext' => [
                    'eventType' => 'CONNECT',
                    'connectionId' => 'abc-123',
                ],
                'queryStringParameters' => [
                    'appId' => 'invalid-app-id',
                ],
            ]
        ));

    $this->assertSent('abc-123', '{"event":"pusher:error","data":"{\"code\":4001,\"message\":\"Application does not exist\"}"}');
});

it('cannot connect from an invalid origin', function () {
    $this->app['config']->set('reverb.apps.apps.0.allowed_origins', ['https://laravel.com']);

    resolve(Server::class)
        ->handle(Request::fromLambdaEvent(
            [
                'requestContext' => [
                    'eventType' => 'CONNECT',
                    'connectionId' => 'abc-123',
                ],
                'queryStringParameters' => [
                    'appId' => 'pusher-key',
                ],
            ]
        ));

    $this->assertSent('abc-123', '{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Origin not allowed\"}"}', 1);
});

it('can connect from a valid origin', function () {
    $this->app['config']->set('reverb.apps.0.allowed_origins', ['laravel.com']);

    resolve(Server::class)
        ->handle(Request::fromLambdaEvent(
            [
                'requestContext' => [
                    'eventType' => 'CONNECT',
                    'connectionId' => 'abc-123',
                ],
                'queryStringParameters' => [
                    'appId' => 'pusher-key',
                ],
                'headers' => [
                    'origin' => 'https://laravel.com',
                ],
            ]
        ));

    $this->assertSent('abc-123', 'connection_established', 1);
});

it('clears application state between requests', function () {
    $this->subscribe('test-channel');

    expect($this->app->make(ChannelManager::class)->app())->toBeNull();
})->todo();
