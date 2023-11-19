<?php

use Laravel\Reverb\Tests\RatchetTestCase;

use function React\Async\await;

uses(RatchetTestCase::class);

it('can return data for a single channel', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":true,"user_count":2,"subscription_count":2,"cache":"{}"}', $response->getBody()->getContents());
});

it('returns unoccupied when no connections', function () {
    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":false,"user_count":0,"subscription_count":0,"cache":"{}"}', $response->getBody()->getContents());
});

it('can return only the requested attributes', function () {
    $this->subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":true,"user_count":1,"subscription_count":1,"cache":"{}"}', $response->getBody()->getContents());

    $response = await($this->signedRequest('channels/test-channel-one?info=cache'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":true,"cache":"{}"}', $response->getBody()->getContents());

    $response = await($this->signedRequest('channels/test-channel-one?info=subscription_count,user_count'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":true,"user_count":1,"subscription_count":1}', $response->getBody()->getContents());
});
