<?php

use Clue\React\Redis\Client;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisClientFactory;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisPubSubProvider;
use React\EventLoop\LoopInterface;

it('resubscribes to the scaling channel on unsubscribe event', function () {

    $channel = 'reverb';
    $subscribingClient = Mockery::mock(Client::class);

    $subscribingClient->shouldReceive('on')
        ->with('unsubscribe', Mockery::on(function ($callback) use ($channel) {
            $callback($channel);

            return true;
        }))->once();

    $subscribingClient->shouldReceive('on')
        ->with('message', Mockery::any())
        ->zeroOrMoreTimes();

    $subscribingClient->shouldReceive('subscribe')
        ->twice()
        ->with($channel);

    $clientFactory = Mockery::mock(RedisClientFactory::class);

    // The first call to make() will return a publishing client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(Mockery::mock(Client::class));

    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn($subscribingClient);

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), $channel);
    $provider->connect(Mockery::mock(LoopInterface::class));

    $provider->subscribe();
});
