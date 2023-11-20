<?php

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Tests\Connection;
use Laravel\Reverb\Tests\ReverbTestCase;
use React\Http\Message\ResponseException;

use function React\Async\await;

uses(ReverbTestCase::class);

it('returns an error when presence channel not provided', function () {
    await($this->signedRequest('channels/test-channel/users'));
})->throws(ResponseException::class);

it('returns the user data', function () {
    $channel = app(ChannelManager::class)
        ->for(app()->make(ApplicationProvider::class)->findByKey('pusher-key'))
        ->find('presence-test-channel');
    $channel->subscribe($connection = new Connection('test-connection-one'), validAuth($connection, 'presence-test-channel', $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Taylor']])), $data);
    $channel->subscribe($connection = new Connection('test-connection-two'), validAuth($connection, 'presence-test-channel', $data = json_encode(['user_id' => 2, 'user_info' => ['name' => 'Joe']])), $data);
    $channel->subscribe($connection = new Connection('test-connection-three'), validAuth($connection, 'presence-test-channel', $data = json_encode(['user_id' => 3, 'user_info' => ['name' => 'Jess']])), $data);

    $response = await($this->signedRequest('channels/presence-test-channel/users'));

    expect($response->getStatusCode())->toBe(200);
    expect($response->getBody()->getContents())->toBe('{"users":[{"id":1},{"id":2},{"id":3}]}');
});
