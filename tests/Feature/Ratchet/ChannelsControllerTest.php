<?php

use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return all channel information', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-one":{"user_count":1},"test-channel-two":{"user_count":1}}}', $response->getBody()->getContents());
});

it('can return filtered channels', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-two');

    $response = await($this->signedRequest('channels?filter_by_prefix=test-channel-t&info=user_count'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-two":{"user_count":1}}}', $response->getBody()->getContents());
});

it('returns empty results if no metrics requested', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-two');

    $response = await($this->signedRequest('channels'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-one":[],"test-channel-two":[]}}', $response->getBody()->getContents());
});
