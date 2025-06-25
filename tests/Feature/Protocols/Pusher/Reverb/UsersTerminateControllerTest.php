<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('returns an error when connection cannot be found', function () {
    await($this->signedPostRequest('channels/users/not-a-user/terminate_connections'));
})->throws(ResponseException::class, exceptionCode: 404);

it('unsubscribes from all channels and terminates a user', function () {
    $connection = connect();
    subscribe('presence-test-channel-one', ['user_id' => '123'], connection: $connection);
    subscribe('test-channel-two', connection: $connection);

    $connection = connect();
    subscribe('presence-test-channel-one', ['user_id' => '456'], connection: $connection);
    subscribe('test-channel-two', connection: $connection);

    expect(collect(channels()->find('presence-test-channel-one')->connections()))->toHaveCount(2);
    expect(collect(channels()->find('test-channel-two')->connections()))->toHaveCount(2);

    $response = await($this->signedPostRequest('users/456/terminate_connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
    expect(collect(channels()->all())->get('presence-test-channel-one')->connections())->toHaveCount(1);
    expect(collect(channels()->all())->get('test-channel-two')->connections())->toHaveCount(1);
    expect($response->getHeader('Content-Length'))->toBe(['2']);
});

it('unsubscribes from all channels across all servers and terminates a user', function () {
    $this->usingRedis();

    $connection = connect();
    subscribe('presence-test-channel-one', ['user_id' => '789'], connection: $connection);
    subscribe('test-channel-two', connection: $connection);

    $connection = connect();
    subscribe('presence-test-channel-one', ['user_id' => '987'], connection: $connection);
    subscribe('test-channel-two', connection: $connection);

    expect(collect(channels()->find('presence-test-channel-one')->connections()))->toHaveCount(2);
    expect(collect(channels()->find('test-channel-two')->connections()))->toHaveCount(2);

    $response = await($this->signedPostRequest('users/987/terminate_connections'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
    expect(collect(channels()->all())->get('presence-test-channel-one')->connections())->toHaveCount(1);
    expect(collect(channels()->all())->get('test-channel-two')->connections())->toHaveCount(1);
    expect($response->getHeader('Content-Length'))->toBe(['2']);
});

it('fails when using an invalid signature', function () {
    $response = await($this->postRequest('users/987/terminate_connections'));

    expect($response->getStatusCode())->toBe(401);
})->throws(ResponseException::class, exceptionCode: 401);
