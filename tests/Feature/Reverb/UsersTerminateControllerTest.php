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
    $this->subscribe('test-channel-one', connection: $connection);
    $this->subscribe('test-channel-two', connection: $connection);

    $connection = $this->connect();
    $this->subscribe('test-channel-one', connection: $connection);
    $this->subscribe('test-channel-two', connection: $connection);

    expect(collect(channelManager()->all())->get('test-channel-one')->connections())->toHaveCount(2);
    expect(collect(channelManager()->all())->get('test-channel-two')->connections())->toHaveCount(2);

    $response = await($this->signedPostRequest("users/{$this->connectionId}/terminate_connections"));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{}', $response->getBody()->getContents());
    expect(collect(channelManager()->all())->get('test-channel-one')->connections())->toHaveCount(1);
    expect(collect(channelManager()->all())->get('test-channel-two')->connections())->toHaveCount(1);
});
