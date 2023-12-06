<?php

use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('returns an error when connection cannot be found', function () {
    await($this->signedPostRequest('channels/users/not-a-user/terminate_connections'));
})->throws(ResponseException::class);

it('unsubscribes from all channels and terminates a user', function () {
    $connection = $this->connect();
    $this->subscribe('presence-test-channel-one', ['user_id' => '123'], connection: $connection);
    $this->subscribe('test-channel-two', connection: $connection);

    $connection = $this->connect();
    $this->subscribe('presence-test-channel-one', ['user_id' => '456'], connection: $connection);
    $this->subscribe('test-channel-two', connection: $connection);
    $connection = connect();
    subscribe('test-channel-one', connection: $connection);
    subscribe('test-channel-two', connection: $connection);

    $connection = connect();
    subscribe('test-channel-one', connection: $connection);
    subscribe('test-channel-two', connection: $connection);

    expect(collect(channelManager()->find('presence-test-channel-one')->connections()))->toHaveCount(2);
    expect(collect(channelManager()->find('test-channel-two')->connections()))->toHaveCount(2);
    expect(collect(channels()->all())->get('test-channel-one')->connections())->toHaveCount(2);
    expect(collect(channels()->all())->get('test-channel-two')->connections())->toHaveCount(2);

    $response = await($this->signedPostRequest('users/456/terminate_connections'));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{}', $response->getBody()->getContents());
    expect(collect(channelManager()->find('presence-test-channel-one')->connections()))->toHaveCount(1);
    expect(collect(channelManager()->find('test-channel-two')->connections()))->toHaveCount(1);
    $response = await($this->signedPostRequest("users/{$connection->socketId()}/terminate_connections"));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{}');
    expect(collect(channels()->all())->get('test-channel-one')->connections())->toHaveCount(1);
    expect(collect(channels()->all())->get('test-channel-two')->connections())->toHaveCount(1);
});
