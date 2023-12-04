<?php

use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can receive an event batch trigger', function () {
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => ['some' => 'data'],
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
            'data' => ['some' => 'data'],
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => ['some' => ['more' => 'data']],
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":{}}');
});

it('can receive an event batch trigger with multiple events and return info for each', function () {
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'presence-test-channel',
            'data' => ['some' => 'data'],
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => ['some' => ['more' => 'data']],
            'info' => 'subscription_count',
        ],
        [
            'name' => 'YetAnotherNewEvent',
            'channel' => 'test-channel-three',
            'data' => ['some' => ['more' => 'data']],
            'info' => 'subscription_count,user_count',
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":[{"user_count":0},{"subscription_count":0},{"subscription_count":0}]}');
});

it('can receive an event batch trigger with multiple events and return info for some', function () {
    $response = await($this->signedPostRequest('batch_events', ['batch' => [
        [
            'name' => 'NewEvent',
            'channel' => 'presence-test-channel',
            'data' => ['some' => 'data'],
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => ['some' => ['more' => 'data']],
        ],
    ]]));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"batch":[{"user_count":0},[]]}');
});
