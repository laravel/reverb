<?php

use Illuminate\Support\Arr;
use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return all channel information', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-one":{},"presence-test-channel-two":{"user_count":1}}}', $response->getBody()->getContents());
});

it('can return filtered channels', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('presence-test-channel-two');
    subscribe('test-channel-one');
    subscribe('test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":{"user_count":1},"test-channel-two":{"user_count":1}}}');
});

it('returns empty results if no metrics requested', function () {
    subscribe('test-channel-one');
    subscribe('test-channel-two');

    $response = await($this->signedRequest('channels'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-one":{},"test-channel-two":{}}}', $response->getBody()->getContents());
});

it('only returns occupied channels', function () {
    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-two');

    $channels = channelManager();
    $connection = Arr::first($channels->connections());
    $channels->unsubscribeFromAll($connection->connection());

    $response = await($this->signedRequest('channels'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{"channels":{"test-channel-two":{}}}', $response->getBody()->getContents());
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"channels":{"test-channel-one":[],"test-channel-two":[]}}');
});
