<?php

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Tests\ReverbTestCase;
use React\Promise\Deferred;

use function Ratchet\Client\connect;
use function React\Async\await;
use function React\Promise\all;

uses(ReverbTestCase::class);

it('can handle connections to different applications', function () {
    $this->connect();
    $this->connect(key: 'pusher-key-2');
    $this->connect(key: 'pusher-key-3', headers: ['Origin' => 'http://laravel.com']);
});

it('can subscribe to a channel', function () {
    $response = $this->subscribe('test-channel');

    $this->assertCount(1, channelManager()->find('test-channel')->connections());

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
        expect($response)->toBe('{"event":"App\\\\Events\\\\TestEvent","data":{"foo":"bar"},"channel":"test-channel"}');
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

    expect(await($promise))->toBe('{"event":"App\\\\Events\\\\TestEvent","data":{"foo":"bar"},"channel":"presence-test-channel"}');
});

it('can respond to a ping', function () {
    $connection = $this->connect();
    $promise = $this->messagePromise($connection);

    $this->send(['event' => 'pusher:ping'], $connection);

    expect(await($promise))->toBe('{"event":"pusher:pong"}');
});

it('it can ping inactive subscribers', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);
    $promise = $this->messagePromise($connection);

    Arr::first(channelManager()->connections())->setLastSeenAt(time() - 60 * 10);

    (new PingInactiveConnections)->handle(channelManager());

    expect(await($promise))->toBe('{"event":"pusher:ping"}');
});

it('it can disconnect inactive subscribers', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel', connection: $connection);
    $promise = $this->disconnectPromise($connection);

    expect(channelManager()->find('test-channel')->connections())->toHaveCount(1);

    Arr::first(channelManager()->connections())->setLastSeenAt(time() - 60 * 10);

    $promiseTwo = $this->messagePromise($connection);
    (new PingInactiveConnections)->handle(
        channelManager()
    );
    expect(await($promiseTwo))->toBe('{"event":"pusher:ping"}');

    $promiseThree = $this->messagePromise($connection);
    (new PruneStaleConnections)->handle(
        channelManager()
    );

    expect(channelManager()->find('test-channel')->connections())->toHaveCount(0);

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

    expect(channelManager()->all())->toHaveCount(4);
    collect(channelManager()->all())->each(function ($channel) use ($connection) {
        expect($channel->connections())->toHaveCount(1);
        expect(collect($channel->connections())->map(fn ($connection) => $connection->id()))->toContain($this->connectionId);
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

    expect(channelManager()->all())->toHaveCount(4);

    expect(channelManager()->find('test-channel')->connections())->toHaveCount(2);
    expect(channelManager()->find('test-channel-2')->connections())->toHaveCount(1);
    expect(channelManager()->find('private-test-channel-3')->connections())->toHaveCount(2);
    expect(channelManager()->find('presence-test-channel-4')->connections())->toHaveCount(1);
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

    $this->assertTrue(
        Str::contains(
            await($promise->promise()),
            '{"event":"pusher:error","data":"{\"code\":4001,\"message\":\"Application does not exist\"}"}'
        )
    );
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

    expect(await($promise))->toBe('{"event":"App\\\\Events\\\\TestEvent","data":{"foo":"bar"},"channel":"presence-test-channel"}');
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
    $connection = await(
        connect('ws://0.0.0.0:8080/app/pusher-key-3')
    );
    $promise = $this->messagePromise($connection);

    expect(await($promise))->toBe('{"event":"pusher:error","data":"{\"code\":4009,\"message\":\"Origin not allowed\"}"}');
});

it('can connect from a valid origin', function () {
    $this->app['config']->set('reverb.apps.0.allowed_origins', ['0.0.0.0']);

    $this->connect();
});

it('cconnections can be limited', function () {
    $this->app['config']->set('reverb.servers.reverb.connection_limit', 1);
    $this->stopServer();
    $this->startServer();
    $this->connect();
    
  $this->connect();  
})->throws('Connection closed before handshake');

it('clears application state between requests', function () {
    $this->subscribe('test-channel');

    expect($this->app->make(ChannelManager::class)->app())->toBeNull();
})->todo();
