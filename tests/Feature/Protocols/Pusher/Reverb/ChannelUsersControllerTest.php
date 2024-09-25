<?php

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Tests\FakeConnection;
use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('returns an error when presence channel not provided', function () {
    subscribe('test-channel');
    await($this->signedRequest('channels/test-channel/users'));
})->throws(ResponseException::class, exceptionCode: 400);

it('returns an error when unoccupied channel provided', function () {
    await($this->signedRequest('channels/presence-test-channel/users'));
})->throws(ResponseException::class, exceptionCode: 404);

it('returns the user data', function () {
    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('reverb-key'))
        ->findOrCreate('presence-test-channel');
    $channel->subscribe($connection = new FakeConnection('test-connection-one'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-two'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-three'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"users":[{"id":1},{"id":2},{"id":3}]}');
});

it('returns the unique user data', function () {
    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('reverb-key'))
        ->findOrCreate('presence-test-channel');
    $channel->subscribe($connection = new FakeConnection('test-connection-one'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-two'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-three'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"users":[{"id":3},{"id":2}]}');
});

it('returns an error when gathering a non-existent presence channel', function () {
    $this->usingRedis();

    subscribe('test-channel');

    await($this->signedRequest('channels/test-channel/users'));
})->throws(ResponseException::class, exceptionCode: 400);

it('returns an error when gathering unoccupied channel provided', function () {
    $this->usingRedis();

    await($this->signedRequest('channels/presence-test-channel/users'));
})->throws(ResponseException::class, exceptionCode: 404);

it('can send the content-length header', function () {
    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('reverb-key'))
        ->findOrCreate('presence-test-channel');
    $channel->subscribe($connection = new FakeConnection('test-connection-one'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-two'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-three'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getHeader('Content-Length'))->toBe(['38']);
});

it('gathers the user data', function () {
    $this->usingRedis();

    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('reverb-key'))
        ->findOrCreate('presence-test-channel');
    $channel->subscribe($connection = new FakeConnection('test-connection-one'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-two'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-three'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"users":[{"id":1},{"id":2},{"id":3}]}');
});

it('gathers the unique user data', function () {
    $this->usingRedis();

    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('reverb-key'))
        ->findOrCreate('presence-test-channel');
    $channel->subscribe($connection = new FakeConnection('test-connection-one'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-two'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-three'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"users":[{"id":2},{"id":3}]}');
});

it('can send the content-length header when gathering results', function () {
    $this->usingRedis();

    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('reverb-key'))
        ->findOrCreate('presence-test-channel');
    $channel->subscribe($connection = new FakeConnection('test-connection-one'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-two'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new FakeConnection('test-connection-three'), validAuth($connection->id(), 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getHeader('Content-Length'))->toBe(['38']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->request('channels/presence-test-channel/users'));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
