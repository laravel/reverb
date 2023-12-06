<?php

use Laravel\Reverb\Tests\ReverbTestCase;

use function React\Async\await;

uses(ReverbTestCase::class);

it('can return data for a single channel', function () {
    subscribe('test-channel-one');
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":2}');
});

it('returns unoccupied when no connections', function () {
    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":false,"subscription_count":0}');
});

it('can return cache channel attributes', function () {
    subscribe('cache-test-channel-one');
    channels()->find('cache-test-channel-one')->broadcast(['some' => 'data']);

    $response = await($this->signedRequest('channels/cache-test-channel-one?info=subscription_count,cache'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1,"cache":{"some":"data"}}');
});

it('can return only the requested attributes', function () {
    subscribe('test-channel-one');

    $response = await($this->signedRequest('channels/test-channel-one?info=user_count,subscription_count,cache'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1}');

    $response = await($this->signedRequest('channels/test-channel-one?info=cache'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true}');

    $response = await($this->signedRequest('channels/test-channel-one?info=subscription_count,user_count'));
    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"occupied":true,"subscription_count":1}');
});
