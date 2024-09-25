<?php

use Illuminate\Support\Arr;
use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return all channel information', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{},"presence-test-channel-two":{"user_count":1}}}');
});

it('can return filtered channels', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{},"presence-test-channel-two":{"user_count":1}}}');
});

it('returns empty results if no metrics requested', function () {
    subscribe('test-channel-one');
    subscribe('test-channel-two');

    $response = await($this->signedRequest('channels'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{},"test-channel-two":{}}}');
});

it('only returns occupied channels', function () {
    subscribe('test-channel-one');
    subscribe('test-channel-two');

    $channels = channels();
    $connection = Arr::first($channels->connections());
    $channels->unsubscribeFromAll($connection->connection());

    $response = await($this->signedRequest('channels'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-two":{}}}');
});

it('can send the content-length header', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getHeader('Content-Length'))->toBe(['81']);
});

it('can gather all channel information', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{},"presence-test-channel-two":{"user_count":1}}}');
});

it('can gather filtered channels', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{},"presence-test-channel-two":{"user_count":1}}}');
});

it('gathers empty results if no metrics requested', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('test-channel-two');

    $response = await($this->signedRequest('channels'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{},"test-channel-two":{}}}');
});

it('only gathers occupied channels', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('test-channel-two');

    $channels = channels();
    $connection = Arr::first($channels->connections());
    $channels->unsubscribeFromAll($connection->connection());

    $response = await($this->signedRequest('channels'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-two":{}}}');
});

it('can send the content-length header when gathering results', function () {
    $this->usingRedis();

    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getHeader('Content-Length'))->toBe(['81']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->request('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
