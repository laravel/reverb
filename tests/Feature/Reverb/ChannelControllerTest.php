<?php

use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return data for a single channel', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":true,"subscription_count":2}', $response->getBody()->getContents());
});

it('returns unoccupied when no connections', function () {
    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":false,"subscription_count":0}', $response->getBody()->getContents());
});

it('can return cache channel attributes', function () {
    $this->subscribe('cache-test-channel-one');
    channelManager()->find('cache-test-channel-one')->broadcast(['some' => 'data']);

    $response = await($this->signedRequest('channels/cache-test-channel-one?info=subscription_count,cache'));
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"occupied":true,"subscription_count":1,"cache":{"some":"data"}}', $response->getBody()->getContents());
});
