<?php

use Laravel\Reverb\Protocols\Pusher\Channels\Channel;

/**
 * Create a mocked Channel that satisfies the Type Hint.
 */
function mockChannel(string $name): Channel
{
    $channel = Mockery::mock(Channel::class);
    $channel->shouldReceive('name')->andReturn($name);

    return $channel;
}

/**
 * Create a fake connection for testing purposes.
 *
 * @return \Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection
 */
function createFakeConnection()
{
    return factory(count: 1)[0];
}
