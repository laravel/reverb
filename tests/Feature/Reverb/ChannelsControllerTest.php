<?php

use Illuminate\Support\Arr;
use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return all channel information', function () {
    subscribe('test-channel-one');
    subscribe('presence-test-channel-two');

    $response = await($this->signedRequest('channels?info=user_count'));

    expect($response->getStatusCode())->toBe(200);
    $this->assertSame('{"channels":{"test-channel-one":{},"presence-test-channel-two":{"user_count":1}}}', $response->getBody()->getContents());
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
