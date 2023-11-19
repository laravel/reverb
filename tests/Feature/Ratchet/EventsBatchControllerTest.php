<?php

use Laravel\Reverb\Tests\RatchetTestCase;

use function React\Async\await;

uses(RatchetTestCase::class);

it('can receive an event batch trigger', function () {
    $response = await($this->signedPostRequest('batch_events', [[
        'name' => 'NewEvent',
        'channel' => 'test-channel',
        'data' => ['some' => 'data'],
    ]]));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{}', $response->getBody()->getContents());
});

it('can receive an event batch trigger with multiple events', function () {
    $response = await($this->signedPostRequest('batch_events', [
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
    ]));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{}', $response->getBody()->getContents());
});

it('can receive an event batch trigger with multiple events and return info for each', function () {
    $response = await($this->signedPostRequest('batch_events', [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
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
    ]));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"batch":[{"user_count":0},{"subscription_count":0},{"user_count":0,"subscription_count":0}]}', $response->getBody()->getContents());
});

it('can receive an event batch trigger with multiple events and return info for some', function () {
    $response = await($this->signedPostRequest('batch_events', [
        [
            'name' => 'NewEvent',
            'channel' => 'test-channel',
            'data' => ['some' => 'data'],
            'info' => 'user_count',
        ],
        [
            'name' => 'AnotherNewEvent',
            'channel' => 'test-channel-two',
            'data' => ['some' => ['more' => 'data']],
        ],
    ]));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"batch":[{"user_count":0},[]]}', $response->getBody()->getContents());
});
