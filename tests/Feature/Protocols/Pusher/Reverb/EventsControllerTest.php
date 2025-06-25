<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can receive and event trigger', function () {
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode(['some' => 'data']),
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('can receive and event trigger for multiple channels', function () {
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('can return user counts when requested', function () {
    subscribe('presence-test-channel-one');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'user_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{"user_count":1},"test-channel-two":{}}}');
});

it('can return subscription counts when requested', function () {
    subscribe('test-channel-two');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'subscription_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{},"test-channel-two":{"subscription_count":1}}}');
});

it('can ignore a subscriber', function () {
    $connection = connect();
    subscribe('test-channel-two', connection: $connection);
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
    ]));

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'socket_id' => $connection->socketId(),
    ]));

    $connection->assertReceived('{"event":"NewEvent","data":"{\"some\":\"data\"}","channel":"test-channel-two"}', 1);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('can ignore a subscriber when publishing events over redis', function () {
    $this->usingRedis();

    $connection = connect();
    subscribe('test-channel-two', connection: $connection);
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
    ]));

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'socket_id' => $connection->socketId(),
    ]));

    $connection->assertReceived('{"event":"NewEvent","data":"{\"some\":\"data\"}","channel":"test-channel-two"}', 1);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('does not fail when ignoring an invalid subscriber', function () {
    $connection = connect();
    subscribe('test-channel-two', connection: $connection);
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
    ]));

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => ['more' => 'data']]),
        'socket_id' => 'invalid-socket-id',
    ]));

    $connection->assertReceived('{"event":"NewEvent","data":"{\"some\":\"data\"}","channel":"test-channel-two"}', 1);
    $connection->assertReceived('{"event":"NewEvent","data":"{\"some\":{\"more\":\"data\"}}","channel":"test-channel-two"}', 1);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('validates invalid data', function ($payload) {
    await($this->signedPostRequest('events', $payload));
})
    ->throws(ResponseException::class, exceptionCode: 422)
    ->with([
        [
            [
                'name' => 'NewEvent',
                'channel' => 'test-channel',
            ],
        ],
        [
            [
                'name' => 'NewEvent',
                'channels' => ['test-channel-one', 'test-channel-two'],
            ],
        ],
        [
            [
                'name' => 'NewEvent',
                'channel' => 'test-channel',
                'data' => json_encode(['some' => 'data']),
                'socket_id' => 1234,
            ],
        ],
        [
            [
                'name' => 'NewEvent',
                'channel' => 'test-channel',
                'data' => json_encode(['some' => 'data']),
                'info' => 1234,
            ],
        ],
    ]);

it('can gather user counts when requested', function () {
    $this->usingRedis();

    subscribe('presence-test-channel-one');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'user_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{"user_count":1},"test-channel-two":{}}}');
});

it('can gather subscription counts when requested', function () {
    $this->usingRedis();

    subscribe('test-channel-two');

    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channels' => ['presence-test-channel-one', 'test-channel-two'],
        'data' => json_encode(['some' => 'data']),
        'info' => 'subscription_count',
    ]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"presence-test-channel-one":{},"test-channel-two":{"subscription_count":1}}}');
});

it('cannot trigger an event over the max message size', function () {
    await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode([str_repeat('a', 10_100)]),
    ]));
})->expectExceptionMessage('HTTP status code 413 (Request Entity Too Large)');

it('can trigger an event within the max message size', function () {
    $this->stopServer();
    $this->startServer(maxRequestSize: 20_000);
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode([str_repeat('a', 10_100)]),
    ], appId: '654321', key: 'reverb-key-2', secret: 'reverb-secret-2'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
});

it('fails when payload is invalid', function () {
    $response = await($this->signedPostRequest('events', null));

    expect($response->getStatusCode())->toBe(500);
})->throws(ResponseException::class, exceptionCode: 500);

it('fails when app cannot be found', function () {
    await($this->signedPostRequest('events', appId: 'invalid-app-id'));
})->throws(ResponseException::class, exceptionCode: 404);

it('can send the content-length header', function () {
    $response = await($this->signedPostRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode(['some' => 'data']),
    ]));

    expect($response->getHeader('Content-Length'))->toBe(['2']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->postRequest('events', [
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => json_encode(['some' => 'data']),
    ]));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
