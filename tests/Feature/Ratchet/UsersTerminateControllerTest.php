<?php

use Illuminate\Support\Arr;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Tests\RatchetTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(RatchetTestCase::class);

it('returns an error when connection cannot be found', function () {
    await($this->signedPostRequest('channels/users/not-a-user/terminate_connections'));
})->throws(ResponseException::class);

it('unsubscribes from all channels and terminates a user', function () {
    $connection = $this->connect();
    $this->subscribe('test-channel-one', connection: $connection);
    $this->subscribe('test-channel-two', connection: $connection);

    $this->subscribe('test-channel-one');
    $this->subscribe('test-channel-two');

    expect($connections = connectionManager()->all())->toHaveCount(3);
    expect(channelManager()->all()->get('test-channel-one')->connections())->toHaveCount(2);
    expect(channelManager()->all()->get('test-channel-two')->connections())->toHaveCount(2);

    $connection = Arr::first($connections);

    $response = await($this->signedPostRequest("users/{$connection->id()}/terminate_connections"));

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('{}', $response->getBody()->getContents());
    expect($connections = connectionManager()->all())->toHaveCount(2);
    expect(channelManager()->all()->get('test-channel-one')->connections())->toHaveCount(1);
    expect(channelManager()->all()->get('test-channel-two')->connections())->toHaveCount(1);
});
