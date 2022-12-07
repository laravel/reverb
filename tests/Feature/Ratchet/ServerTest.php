<?php

use Illuminate\Support\Str;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Tests\RatchetTestCase;
use function React\Async\await;
use function React\Promise\all;

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

    $this->assertCount(1, channelManager()->connections(ChannelBroker::create('test-channel')));

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
