<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

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

it('can send the content-length header', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('connections'));

    expect($response->getHeader('Content-Length'))->toBe(['17']);
});

it('can gather a connection count', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"connections":2}');
});

it('can gather the correct connection count when subscribed to multiple channels', function () {
    $this->usingRedis();

    $connection = connect();
    subscribe('test-channel-one', connection: $connection);
    subscribe('presence-test-channel-two', connection: $connection);

    $response = await($this->signedRequest('connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"connections":1}');
});

it('can send the content-length header when gathering results', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('connections'));

    expect($response->getHeader('Content-Length'))->toBe(['17']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->request('connections'));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
