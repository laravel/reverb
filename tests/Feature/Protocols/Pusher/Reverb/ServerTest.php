<?php

use Illuminate\Support\Arr;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Tests\ReverbTestCase;
use Ratchet\RFC6455\Messaging\Frame;
use React\Http\Message\ResponseException;
use React\Promise\Deferred;

use function Ratchet\Client\connect as wsConnect;
use function React\Async\await;

uses(ReverbTestCase::class);

it('can handle connections to different applications', function () {
    connect(key: 'reverb-key-2');
    connect(key: 'reverb-key-3', headers: ['Origin' => 'http://laravel.com']);
});

it('can subscribe to a channel', function () {
    $response = subscribe('test-channel');

    expect(channels()->find('test-channel')->connections())->toHaveCount(1);
    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"test-channel"}');
});

it('can subscribe to a private channel', function () {
    $response = subscribe('private-test-channel');

    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"private-test-channel"}');
});

it('can subscribe to a presence channel', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User']];
    $response = subscribe('presence-test-channel', data: $data);

    expect($response)->toContain('pusher_internal:subscription_succeeded');
    expect($response)->toContain('"hash\":{\"1\":{\"name\":\"Test User\"}}');
});

it('can subscribe to a cache channel', function () {
    $response = subscribe('cache-test-channel');

    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"cache-test-channel"}');
});

it('can subscribe to a private cache channel', function () {
    $response = subscribe('private-cache-test-channel');

    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"private-cache-test-channel"}');
});

it('can subscribe to a presence cache channel', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User']];
    $response = subscribe('presence-cache-test-channel', data: $data);

    expect($response)->toContain('pusher_internal:subscription_succeeded');
    expect($response)->toContain('"hash\":{\"1\":{\"name\":\"Test User\"}}');
});

it('can notify other subscribers of a presence channel when a new member joins', function () {
    $connectionOne = connect();
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']];
    subscribe('presence-test-channel', data: $data, connection: $connectionOne);

    $connectionTwo = connect();
    $data = ['user_id' => 2, 'user_info' => ['name' => 'Test User 2']];
    subscribe('presence-test-channel', data: $data, connection: $connectionTwo);

    $data = ['user_id' => 3, 'user_info' => ['name' => 'Test User 3']];
    subscribe('presence-test-channel', data: $data);

    $connectionOne->assertReceived('{"event":"pusher_internal:member_added","data":"{\"user_id\":2,\"user_info\":{\"name\":\"Test User 2\"}}","channel":"presence-test-channel"}');
    $connectionTwo->assertReceived('{"event":"pusher_internal:member_added","data":"{\"user_id\":3,\"user_info\":{\"name\":\"Test User 3\"}}","channel":"presence-test-channel"}');
});

it('can notify other subscribers of a presence channel when a member leaves', function () {
    $connectionOne = connect();
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']];
    subscribe('presence-test-channel', data: $data, connection: $connectionOne);

    $connectionTwo = connect();
    $data = ['user_id' => 2, 'user_info' => ['name' => 'Test User 2']];
    subscribe('presence-test-channel', data: $data, connection: $connectionTwo);

    $connectionThree = connect();
    $data = ['user_id' => 3, 'user_info' => ['name' => 'Test User 3']];
    subscribe('presence-test-channel', data: $data, connection: $connectionThree);

    $connectionOne->assertReceived('{"event":"pusher_internal:member_added","data":"{\"user_id\":2,\"user_info\":{\"name\":\"Test User 2\"}}","channel":"presence-test-channel"}');
    $connectionTwo->assertReceived('{"event":"pusher_internal:member_added","data":"{\"user_id\":3,\"user_info\":{\"name\":\"Test User 3\"}}","channel":"presence-test-channel"}');

    disconnect($connectionThree);

    $connectionOne->assertReceived('{"event":"pusher_internal:member_removed","data":"{\"user_id\":3}","channel":"presence-test-channel"}');
    $connectionTwo->assertReceived('{"event":"pusher_internal:member_removed","data":"{\"user_id\":3}","channel":"presence-test-channel"}');
});

it('can receive a cached message when joining a cache channel', function () {
    $connection = connect();
    subscribe('cache-test-channel');

    $this->triggerEvent(
        'cache-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    subscribe('cache-test-channel', connection: $connection);

    $connection->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"cache-test-channel"}');
});

it('can receive a cached message when joining a private cache channel', function () {
    $connection = connect();
    subscribe('private-cache-test-channel');

    $this->triggerEvent(
        'private-cache-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    subscribe('private-cache-test-channel', connection: $connection);

    $connection->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"private-cache-test-channel"}');
});

it('can receive a cached message when joining a presence cache channel', function () {
    $connection = connect();
    subscribe('presence-cache-test-channel');

    $this->triggerEvent(
        'presence-cache-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    subscribe('presence-cache-test-channel', connection: $connection);

    $connection->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"presence-cache-test-channel"}');
});

it('can receive a cache missed message when joining a cache channel with an empty cache', function () {
    $connection = connect();
    subscribe('cache-test-channel', connection: $connection);

    $connection->assertReceived('{"event":"pusher:cache_miss","channel":"cache-test-channel"}');
});

it('can receive a cache missed message when joining a private cache channel with an empty cache', function () {
    $connection = connect();
    subscribe('private-cache-test-channel', connection: $connection);

    $connection->assertReceived('{"event":"pusher:cache_miss","channel":"private-cache-test-channel"}');
});

it('can receive a cache missed message when joining a presence cache channel with an empty cache', function () {
    $connection = connect();
    subscribe('presence-cache-test-channel', connection: $connection);

    $connection->assertReceived('{"event":"pusher:cache_miss","channel":"presence-cache-test-channel"}');
});

it('can receive a message broadcast from the server', function () {
    $connectionOne = connect();
    subscribe('test-channel', connection: $connectionOne);

    $connectionTwo = connect();
    subscribe('test-channel', connection: $connectionTwo);

    $connectionThree = connect();
    subscribe('test-channel', connection: $connectionThree);

    $this->triggerEvent(
        'test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    $connectionOne->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"test-channel"}');
    $connectionTwo->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"test-channel"}');
    $connectionThree->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"test-channel"}');
});

it('can handle an event', function () {
    $connection = connect();
    subscribe('presence-test-channel', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    $this->triggerEvent(
        'presence-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    $connection->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"presence-test-channel"}');
});

it('can respond to a ping', function () {
    $connection = connect();

    send(['event' => 'pusher:ping'], $connection);

    $connection->assertReceived('{"event":"pusher:pong"}');
});

it('it can ping inactive subscribers', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);

    Arr::first(channels()->connections())->setLastSeenAt(time() - 60 * 10);

    (new PingInactiveConnections)->handle(channels());

    $connection->assertReceived('{"event":"pusher:ping"}');
});

it('it can disconnect inactive subscribers', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);

    expect(channels()->find('test-channel')->connections())->toHaveCount(1);

    Arr::first(channels()->connections())->setLastSeenAt(time() - 60 * 10);

    (new PingInactiveConnections)->handle(channels());

    (new PruneStaleConnections)->handle(channels());

    expect(channels()->find('test-channel'))->toBeNull();

    $connection->assertReceived('{"event":"pusher:ping"}');
    $connection->assertReceived('{"event":"pusher:error","data":"{\"code\":4201,\"message\":\"Pong reply not received in time\"}"}');
});

it('can handle a client whisper', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);

    $newConnection = connect();
    subscribe('test-channel', connection: $newConnection);

    $connection->send(
        json_encode([
            'event' => 'client-start-typing',
            'channel' => 'test-channel',
            'data' => [
                'id' => 123,
                'name' => 'Joe Dixon',
            ],
        ])
    );

    $newConnection->assertReceived('{"event":"client-start-typing","channel":"test-channel","data":{"id":123,"name":"Joe Dixon"}}');
});

it('can subscribe a connection to multiple channels', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);
    subscribe('test-channel-2', connection: $connection);
    subscribe('private-test-channel-3', connection: $connection, data: ['foo' => 'bar']);
    subscribe('presence-test-channel-4', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    expect(channels()->all())->toHaveCount(4);
    collect(channels()->all())->each(function ($channel) use ($connection) {
        expect($channel->connections())->toHaveCount(1);
        expect(collect($channel->connections())->map(fn ($conn) => $conn->id()))->toContain($connection->socketId());
    });
});

it('can subscribe multiple connections to multiple channels', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);
    subscribe('test-channel-2', connection: $connection);
    subscribe('private-test-channel-3', connection: $connection, data: ['foo' => 'bar']);
    subscribe('presence-test-channel-4', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    $connection = connect();
    subscribe('test-channel', connection: $connection);
    subscribe('private-test-channel-3', connection: $connection, data: ['foo' => 'bar']);

    expect(channels()->all())->toHaveCount(4);

    expect(channels()->find('test-channel')->connections())->toHaveCount(2);
    expect(channels()->find('test-channel-2')->connections())->toHaveCount(1);
    expect(channels()->find('private-test-channel-3')->connections())->toHaveCount(2);
    expect(channels()->find('presence-test-channel-4')->connections())->toHaveCount(1);
});

it('fails to subscribe to a private channel with invalid auth signature', function () {
    $response = subscribe('private-test-channel', auth: 'invalid-signature');

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to subscribe to a presence channel with invalid auth signature', function () {
    $response = subscribe('presence-test-channel', auth: 'invalid-signature');

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to subscribe to a private cache channel with invalid auth signature', function () {
    $response = subscribe('private-cache-test-channel', auth: 'invalid-signature');

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to subscribe to a presence cache channel with invalid auth signature', function () {
    $response = subscribe('presence-cache-test-channel', auth: 'invalid-signature');

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to connect when an invalid application is provided', function () {
    $promise = new Deferred;

    $connection = await(
        wsConnect('ws://0.0.0.0:8080/app/invalid-key')
    );

    $connection->on('message', function ($message) use ($promise) {
        $promise->resolve((string) $message);
    });

    expect(await($promise->promise()))
        ->toBe('{"event":"pusher:error","data":"{\"code\":4001,\"message\":\"Application does not exist\"}"}');
});

it('can publish and subscribe to a triggered event', function () {
    $this->usingRedis();

    $connection = connect();
    subscribe('presence-test-channel', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    $this->triggerEvent(
        'presence-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    $connection->assertReceived('{"event":"App\\\\Events\\\\TestEvent","data":"{\"foo\":\"bar\"}","channel":"presence-test-channel"}');
});

it('can publish and subscribe to a client whisper', function () {
    $this->usingRedis();

    $connection = connect();
    subscribe('test-channel', connection: $connection);

    $newConnection = connect();
    subscribe('test-channel', connection: $newConnection);

    $connection->send(
        json_encode([
            'event' => 'client-start-typing',
            'channel' => 'test-channel',
            'data' => [
                'id' => 123,
                'name' => 'Joe Dixon',
            ],
        ])
    );

    $newConnection->assertReceived('{"event":"client-start-typing","channel":"test-channel","data":{"id":123,"name":"Joe Dixon"}}');
});

it('cannot connect from an invalid origin', function () {
    $connection = await(
        wsConnect('ws://0.0.0.0:8080/app/reverb-key-3')
    );

    $promise = new Deferred;

    $connection->on('message', function ($message) use ($promise) {
        $promise->resolve((string) $message);
    });

    $message = await($promise->promise());

    expect($message)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Origin not allowed\"}"}');
});

it('can connect from a valid origin', function () {
    $this->app['config']->set('reverb.apps.0.allowed_origins', ['0.0.0.0']);

    connect();
});

it('limits the size of messages', function () {
    $connection = connect(key: 'reverb-key-3', headers: ['Origin' => 'http://laravel.com']);
    send(['This message is waaaaaay longer than the 1 byte limit'], $connection);

    $connection->assertReceived('Maximum message size exceeded');
});

it('removes a channel when no subscribers remain', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);

    expect(channels()->all())->toHaveCount(1);

    unsubscribe('test-channel', $connection);

    $connection->await();

    expect(channels()->all())->toHaveCount(0);
});

it('fails to subscribe to private channel with no auth token', function () {
    $response = send([
        'event' => 'pusher:subscribe',
        'data' => [
            'channel' => 'private-test-channel',
            'auth' => null,
        ],
    ], connect());

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to subscribe to presence channel with no auth token', function () {
    $response = send([
        'event' => 'pusher:subscribe',
        'data' => [
            'channel' => 'presence-test-channel',
            'auth' => null,
        ],
    ], connect());

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('rejects messages over the max allowed size', function () {
    $connection = connect();

    $response = send([
        'event' => 'pusher:subscribe',
        'data' => [
            'channel' => 'my-channel',
            'channel_data' => json_encode([str_repeat('a', 10_100)]),
        ],
    ], $connection);

    expect($response)->toBe('Maximum message size exceeded');
});

it('allows message within the max allowed size', function () {
    $connection = connect(key: 'reverb-key-2');

    $response = send([
        'event' => 'pusher:subscribe',
        'data' => [
            'channel' => 'my-channel',
            'channel_data' => json_encode([str_repeat('a', 20_000)]),
        ],
    ], $connection);

    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"my-channel"}');
});

it('buffers large requests correctly', function () {
    $this->stopServer();
    $this->startServer(maxRequestSize: 200_000);
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode([str_repeat('a', 150_000)]),
    ], appId: '654321', key: 'reverb-key-2', secret: 'reverb-secret-2'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('server listens on a specific path', function () {
    $this->stopServer();
    $this->startServer(path: 'ws');
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode(['data' => 'data']),
    ], pathPrefix: '/ws', appId: '654321', key: 'reverb-key-2', secret: 'reverb-secret-2'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('returns an error when the path prefix is not included in the request', function () {
    $this->stopServer();
    $this->startServer(path: 'ws');
    await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode(['data' => 'data']),
    ], appId: '654321', key: 'reverb-key-2', secret: 'reverb-secret-2'));
})->throws(ResponseException::class, exceptionCode: 404);

it('subscription_succeeded event contains unique list of users', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User']];
    subscribe('presence-test-channel', data: $data);
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User']];
    $response = subscribe('presence-test-channel', data: $data);

    expect($response)->toContain('pusher_internal:subscription_succeeded');
    expect($response)->toContain('"count\":1');
    expect($response)->toContain('"ids\":[1]');
    expect($response)->toContain('"hash\":{\"1\":{\"name\":\"Test User\"}}');
});

it('can handle a ping control frame', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);
    $channels = channels();
    $managedConnection = Arr::first($channels->connections());
    $subscribedAt = $managedConnection->lastSeenAt();
    sleep(1);
    $connection->send(new Frame('', opcode: Frame::OP_PING));

    $connection->assertPonged();
    expect($managedConnection->lastSeenAt())->toBeGreaterThan($subscribedAt);
});

it('can handle a pong control frame', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);
    $channels = channels();
    $managedConnection = Arr::first($channels->connections());
    $subscribedAt = $managedConnection->lastSeenAt();
    sleep(1);
    $connection->send(new Frame('', opcode: Frame::OP_PONG));

    $connection->assertNotPinged();
    $connection->assertNotPonged();
    expect($managedConnection->lastSeenAt())->toBeGreaterThan($subscribedAt);
});

it('uses pusher control messages by default', function () {
    $connection = connect();
    subscribe('test-channel', connection: $connection);

    $channels = channels();
    Arr::first($channels->connections())->setLastSeenAt(time() - 60 * 10);

    (new PingInactiveConnections)->handle($channels);

    $connection->assertReceived('{"event":"pusher:ping"}');
    $connection->assertNotPinged();
});

it('uses control frames when the client prefers', function () {
    $connection = connect();
    $connection->send(new Frame('', opcode: Frame::OP_PING));
    subscribe('test-channel', connection: $connection);

    $channels = channels();
    Arr::first($channels->connections())->setLastSeenAt(time() - 60 * 10);

    (new PingInactiveConnections)->handle($channels);

    $connection->assertPinged();
    $connection->assertNotReceived('{"event":"pusher:ping"}');
});

it('sets the x-powered-by header', function () {
    $connection = connect();

    expect($connection->connection->response->getHeader('X-Powered-By')[0])->toBe('Laravel Reverb');
});
