<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\ConnectionManager;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Tests\RatchetTestCase;
use function Ratchet\Client\connect;
use function React\Async\await;
use function React\Promise\all;
use React\Promise\Deferred;

uses(RatchetTestCase::class);

it('can handle a new connection', function () {
    $this->connect();

    $this->assertCount(1, connectionManager()->all());
});

it('can handle multiple new connections', function () {
    $this->connect();
    $this->connect();

    $this->assertCount(2, connectionManager()->all());
});

it('can handle connections to different applications', function () {
    $this->connect();
    $this->connect(key: 'pusher-key-2');

    foreach (Application::all() as $app) {
        $this->assertCount(1, connectionManager()->for($app)->all());
    }
});

it('can subscribe to a channel', function () {
    $response = $this->subscribe('test-channel');

    $this->assertCount(1, connectionManager()->all());

    $this->assertCount(1, channelManager()->connectionKeys(ChannelBroker::create('test-channel')));

    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","channel":"test-channel"}');
});

it('can subscribe to a private channel', function () {
    $response = $this->subscribe('private-test-channel');

    expect($response)->toBe('{"event":"pusher_internal:subscription_succeeded","channel":"private-test-channel"}');
});

it('can subscribe to a presence channel', function () {
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User']];
    $response = $this->subscribe('presence-test-channel', data: $data);

    expect(Str::contains($response, 'pusher_internal:subscription_succeeded'))->toBeTrue();
    expect(Str::contains($response, '"hash\":{\"1\":{\"name\":\"Test User\"}}'))->toBeTrue();
});

it('can notify other subscribers of a presence channel when a new member joins', function () {
    $connectionOne = $this->connect();
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']];
    $this->subscribe('presence-test-channel', data: $data, connection: $connectionOne);
    $promiseOne = $this->messagePromise($connectionOne);

    $connectionTwo = $this->connect();
    $data = ['user_id' => 2, 'user_info' => ['name' => 'Test User 2']];
    $this->subscribe('presence-test-channel', data: $data, connection: $connectionTwo);
    $promiseTwo = $this->messagePromise($connectionTwo);

    $connectionThree = $this->connect();
    $data = ['user_id' => 3, 'user_info' => ['name' => 'Test User 3']];
    $this->subscribe('presence-test-channel', data: $data, connection: $connectionThree);

    expect(await($promiseOne))->toBe('{"event":"pusher_internal:member_added","data":{"user_id":2,"user_info":{"name":"Test User 2"}},"channel":"presence-test-channel"}');
    expect(await($promiseTwo))->toBe('{"event":"pusher_internal:member_added","data":{"user_id":3,"user_info":{"name":"Test User 3"}},"channel":"presence-test-channel"}');
});

it('can notify other subscribers of a presence channel when a member leaves', function () {
    $connectionOne = $this->connect();
    $data = ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']];
    $this->subscribe('presence-test-channel', data: $data, connection: $connectionOne);
    $promiseOne = $this->messagePromise($connectionOne);

    $connectionTwo = $this->connect();
    $data = ['user_id' => 2, 'user_info' => ['name' => 'Test User 2']];
    $this->subscribe('presence-test-channel', data: $data, connection: $connectionTwo);
    $promiseTwo = $this->messagePromise($connectionTwo);

    $connectionThree = $this->connect();
    $data = ['user_id' => 3, 'user_info' => ['name' => 'Test User 3']];
    $this->subscribe('presence-test-channel', data: $data, connection: $connectionThree);

    expect(await($promiseOne))->toBe('{"event":"pusher_internal:member_added","data":{"user_id":2,"user_info":{"name":"Test User 2"}},"channel":"presence-test-channel"}');
    expect(await($promiseTwo))->toBe('{"event":"pusher_internal:member_added","data":{"user_id":3,"user_info":{"name":"Test User 3"}},"channel":"presence-test-channel"}');

    $promiseThree = $this->messagePromise($connectionOne);
    $promiseFour = $this->messagePromise($connectionOne);

    $connectionThree->close();

    expect(await($promiseThree))->toBe('{"event":"pusher_internal:member_removed","data":{"user_id":3},"channel":"presence-test-channel"}');
    expect(await($promiseFour))->toBe('{"event":"pusher_internal:member_removed","data":{"user_id":3},"channel":"presence-test-channel"}');
});

it('can receive a message broadcast from the server', function () {
    $connectionOne = $this->connect();
    $this->subscribe('test-channel', connection: $connectionOne);
    $promiseOne = $this->messagePromise($connectionOne);

    $connectionTwo = $this->connect();
    $this->subscribe('test-channel', connection: $connectionTwo);
    $promiseTwo = $this->messagePromise($connectionTwo);

    $connectionThree = $this->connect();
    $this->subscribe('test-channel', connection: $connectionThree);
    $promiseThree = $this->messagePromise($connectionThree);

    $this->triggerEvent(
        'test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    foreach (await(all([$promiseOne, $promiseTwo, $promiseThree])) as $response) {
        expect($response)->toBe('{"event":"App\\\\Events\\\\TestEvent","channel":"test-channel","data":{"foo":"bar"}}');
    }
});

it('can handle an event', function () {
    $connection = $this->connect();
    $this->subscribe('presence-test-channel', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);
    $promise = $this->messagePromise($connection);

    $this->triggerEvent(
        'presence-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    expect(await($promise))->toBe('{"event":"App\\\\Events\\\\TestEvent","channel":"presence-test-channel","data":{"foo":"bar"}}');
});

it('can respond to a ping', function () {
    $connection = $this->connect();
    $promise = $this->messagePromise($connection);

    $this->send(['event' => 'pusher:ping'], $connection);

    expect(await($promise))->toBe('{"event":"pusher:pong"}');
});

it('it can ping inactive subscribers', function () {
    $connection = $this->connect();
    $promise = $this->messagePromise($connection);

    Carbon::setTestNow(now()->addMinutes(10));

    (new PingInactiveConnections)->handle(
        connectionManager()
    );

    expect(await($promise))->toBe('{"event":"pusher:ping"}');
});

it('it can disconnect inactive subscribers', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);
    $promise = $this->disconnectPromise($connection);

    expect(connectionManager()->all())->toHaveCount(1);
    expect(channelManager()->connectionKeys(ChannelBroker::create('test-channel')))->toHaveCount(1);

    Carbon::setTestNow(now()->addMinutes(10));

    $promiseTwo = $this->messagePromise($connection);
    (new PingInactiveConnections)->handle(
        connectionManager()
    );
    expect(await($promiseTwo))->toBe('{"event":"pusher:ping"}');

    $promiseThree = $this->messagePromise($connection);
    (new PruneStaleConnections)->handle(
        connectionManager(),
        channelManager()
    );

    expect(connectionManager()->all())->toHaveCount(0);
    expect(channelManager()->connectionKeys(ChannelBroker::create('test-channel')))->toHaveCount(0);

    expect(await($promiseThree))->toBe('{"event":"pusher:error","data":"{\"code\":4201,\"message\":\"Pong reply not received in time\"}"}');
    expect(await($promise))->toBe('Connection Closed.');
});

it('can handle a client whisper', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);

    $newConnection = $this->connect();
    $this->subscribe('test-channel', connection: $newConnection);
    $promise = $this->messagePromise($newConnection);

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

    expect(await($promise))->toBe('{"event":"client-start-typing","channel":"test-channel","data":{"id":123,"name":"Joe Dixon"}}');
});

it('can subscribe a connection to multiple channels', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);
    $this->subscribe('test-channel-2', connection: $connection);
    $this->subscribe('private-test-channel-3', connection: $connection, data: ['foo' => 'bar']);
    $this->subscribe('presence-test-channel-4', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    expect(connectionManager()->all())->toHaveCount(1);
    expect(channelManager()->all())->toHaveCount(4);

    $connection = connectionManager()->all()->first();

    channelManager()->all()->each(function ($channel) use ($connection) {
        expect(channelManager()->connectionKeys($channel))->toHaveCount(1);
        expect(channelManager()->connectionKeys($channel)->map(fn ($conn, $index) => (string) $index))->toContain($connection->identifier());
    });
});

it('can subscribe multiple connections to multiple channels', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);
    $this->subscribe('test-channel-2', connection: $connection);
    $this->subscribe('private-test-channel-3', connection: $connection, data: ['foo' => 'bar']);
    $this->subscribe('presence-test-channel-4', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);

    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);
    $this->subscribe('private-test-channel-3', connection: $connection, data: ['foo' => 'bar']);

    expect(connectionManager()->all())->toHaveCount(2);
    expect(channelManager()->all())->toHaveCount(4);

    expect(channelManager()->connectionKeys(ChannelBroker::create('test-channel')))->toHaveCount(2);
    expect(channelManager()->connectionKeys(ChannelBroker::create('test-channel-2')))->toHaveCount(1);
    expect(channelManager()->connectionKeys(ChannelBroker::create('private-test-channel-3')))->toHaveCount(2);
    expect(channelManager()->connectionKeys(ChannelBroker::create('presence-test-channel-4')))->toHaveCount(1);
});

it('fails to subscribe to a private channel with invalid auth signature', function () {
    $response = $this->subscribe('private-test-channel', auth: 'invalid-signature');

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to subscribe to a presence channel with invalid auth signature', function () {
    $response = $this->subscribe('presence-test-channel', auth: 'invalid-signature');

    expect($response)->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Connection is unauthorized\"}"}');
});

it('fails to connect when an invalid application is provided', function () {
    $promise = new Deferred();

    $connection = await(
        connect('ws://0.0.0.0:8080/app/invalid-key')
    );

    $connection->on('message', function ($message) use ($promise) {
        $promise->resolve((string) $message);
    });

    expect(await($promise->promise()))->toBe('{"event":"pusher:error","data":"{\"code\":4001,\"message\":\"Application does not exist\"}"}');
});

it('can publish and subscribe to a triggered event', function () {
    $this->usingRedis();

    $connection = $this->connect();
    $this->subscribe('presence-test-channel', connection: $connection, data: ['user_id' => 1, 'user_info' => ['name' => 'Test User 1']]);
    $promise = $this->messagePromise($connection);

    $this->triggerEvent(
        'presence-test-channel',
        'App\\Events\\TestEvent',
        ['foo' => 'bar']
    );

    expect(await($promise))->toBe('{"event":"App\\\\Events\\\\TestEvent","channel":"presence-test-channel","data":{"foo":"bar"}}');
});

it('can publish and subscribe to a client whisper', function () {
    $this->usingRedis();

    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);

    $newConnection = $this->connect();
    $this->subscribe('test-channel', connection: $newConnection);
    $promise = $this->messagePromise($newConnection);

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

    expect(await($promise))->toBe('{"event":"client-start-typing","channel":"test-channel","data":{"id":123,"name":"Joe Dixon"}}');
});

it('cannot connect from an invalid origin', function () {
    $this->app['config']->set('reverb.apps.0.allowed_origins', ['https://laravel.com']);

    $connection = await(
        connect('ws://0.0.0.0:8080/app/pusher-key')
    );
    $promise = $this->messagePromise($connection);

    expect(await($promise))->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Origin not allowed\"}"}');
});

it('can connect from a valid origin', function () {
    $this->app['config']->set('reverb.apps.0.allowed_origins', ['0.0.0.0']);

    $this->connect();
});

it('clears application state between requests', function () {
    $this->subscribe('test-channel');

    expect($this->app->make(ConnectionManager::class)->app())->toBeNull();
    expect($this->app->make(ChannelManager::class)->app())->toBeNull();
});
