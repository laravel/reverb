<?php

use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return a connection count', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"connections":2}');
});

it('can return the correct connection count when subscribed to multiple channels', function () {
    $connection = connect();
    subscribe('test-channel-one', connection: $connection);
    subscribe('presence-test-channel-two', connection: $connection);

    $response = await($this->signedRequest('connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"connections":1}');
});

it('can return a connection count from all subscribers', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"connections":2}');
});
