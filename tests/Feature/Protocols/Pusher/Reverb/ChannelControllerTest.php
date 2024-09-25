<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return data for a single channel', function () {
    subscribe('test-channel-one');
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":2}');
});

it('returns unoccupied when no connections', function () {
    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":false}');
});

it('can return cache channel attributes', function () {
    subscribe('cache-test-channel-one');
    channels()->find('cache-test-channel-one')->broadcast(['some' => 'data']);

    $response = await($this->signedRequest('channels/cache-test-channel-one?info=subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1,"cache":{"some":"data"}}');
});

it('can return presence channel attributes', function () {
    subscribe('presence-test-channel-one', ['id' => 123]);
    subscribe('presence-test-channel-one', ['id' => 123]);

    $response = await($this->signedRequest('channels/presence-test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"user_count":1}');
});

it('can return only the requested attributes', function () {
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1}');

    $response = await($this->signedRequest('channels/test-channel-one?info=cache'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true}');

    $response = await($this->signedRequest('channels/test-channel-one?info=subscription_count,user_count'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1}');
});

it('can send the content-length header', function () {
    subscribe('test-channel-one');
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getHeader('Content-Length'))->toBe(['40']);
});

it('can gather data for a single channel', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":2}');
});

it('gathers unoccupied when no connections', function () {
    $this->usingRedis();

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":false}');
});

it('can gather cache channel attributes', function () {
    $this->usingRedis();

    subscribe('cache-test-channel-one');
    channels()->find('cache-test-channel-one')->broadcast(['some' => 'data']);

    $response = await($this->signedRequest('channels/cache-test-channel-one?info=subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1,"cache":{"some":"data"}}');
});

it('can gather presence channel attributes', function () {
    $this->usingRedis();

    subscribe('presence-test-channel-one', ['id' => 123]);
    subscribe('presence-test-channel-one', ['id' => 123]);

    $response = await($this->signedRequest('channels/presence-test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"user_count":1}');
});

it('can gather only the requested attributes', function () {
    $this->usingRedis();

    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1}');

    $response = await($this->signedRequest('channels/test-channel-one?info=cache'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true}');

    $response = await($this->signedRequest('channels/test-channel-one?info=subscription_count,user_count'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1}');
});

it('can send the content-length header when gathering results', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getHeader('Content-Length'))->toBe(['40']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->request('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
