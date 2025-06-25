<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can receive an event batch trigger', function () {
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => json_encode(['some' => 'data']),
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":{}}');
});

it('can receive an event batch trigger with multiple events', function () {
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => json_encode(['some' => 'data']),
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => json_encode(['some' => ['more' => 'data']]),
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":{}}');
});

it('can receive an event batch trigger with multiple events and return info for each', function () {
    subscribe('presence-test-channel');
    subscribe('test-channel-two');
    subscribe('test-channel-three');
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'presence-test-channel',
            'data' => json_encode(['some' => 'data']),
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => json_encode(['some' => ['more' => 'data']]),
            'info' => 'subscription_count',
        ],
        [
            'name' => 'YetAnotherNewEvent',
            'channel' => 'test-channel-three',
            'data' => json_encode(['some' => ['more' => 'data']]),
            'info' => 'subscription_count,user_count',
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":[{"user_count":1},{"subscription_count":1},{"subscription_count":1}]}');
});

it('can receive an event batch trigger with multiple events and return info for some', function () {
    subscribe('presence-test-channel');
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'presence-test-channel',
            'data' => json_encode(['some' => 'data']),
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => json_encode(['some' => ['more' => 'data']]),
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":[{"user_count":1},{}]}');
});

it('can send the content-length header', function () {
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => json_encode(['some' => 'data']),
        ],
    ]]));

    expect($response->getHeader('Content-Length'))->toBe(['12']);
});

it('can receive an event batch trigger with multiple events and gather info for each', function () {
    $this->usingRedis();

    subscribe('presence-test-channel');
    subscribe('test-channel-two');
    subscribe('test-channel-three');
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'presence-test-channel',
            'data' => json_encode(['some' => 'data']),
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => json_encode(['some' => ['more' => 'data']]),
            'info' => 'subscription_count',
        ],
        [
            'name' => 'YetAnotherNewEvent',
            'channel' => 'test-channel-three',
            'data' => json_encode(['some' => ['more' => 'data']]),
            'info' => 'subscription_count,user_count',
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":[{"user_count":1},{"subscription_count":1},{"subscription_count":1}]}');
});

it('can receive an event batch trigger with multiple events and gather info for some', function () {
    $this->usingRedis();

    subscribe('presence-test-channel');
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'presence-test-channel',
            'data' => json_encode(['some' => 'data']),
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => json_encode(['some' => ['more' => 'data']]),
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":[{"user_count":1},{}]}');
});

it('fails when payload is invalid', function () {
    $response = await($this->signedPostRequest('batch_events', null));

    expect($response->getStatusCode())->toBe(500);
})->throws(ResponseException::class, exceptionCode: 500);

it('can send the content-length header when gathering results', function () {
    $this->usingRedis();

    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => json_encode(['some' => 'data']),
        ],
    ]]));

    expect($response->getHeader('Content-Length'))->toBe(['12']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->postRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => json_encode(['some' => 'data']),
        ],
    ]]));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
