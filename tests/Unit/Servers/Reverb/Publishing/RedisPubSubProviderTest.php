<?php

use Clue\React\Redis\Client;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisClientFactory;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisPubSubProvider;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;

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

    $subscribingClient->shouldReceive('on')
        ->with('close', Mockery::any())
        ->zeroOrMoreTimes();

    $subscribingClient->shouldReceive('subscribe')
        ->twice()
        ->with($channel);

    $clientFactory = Mockery::mock(RedisClientFactory::class);

    // The first call to make() will return a publishing client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve($subscribingClient)));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), $channel);
    $provider->connect(Mockery::mock(LoopInterface::class));
});

it('can successfully reconnect', function () {})->todo();

it('can timeout and fail when unable to reconnect', function () {})->todo();

it('queues subscription events', function () {})->todo();

it('can process queued subscription events', function () {})->todo();

it('queues publish events', function () {})->todo();

it('can process queued publish events', function () {})->todo();

it('does not attempt to reconnect after a controlled disconnection', function () {})->todo();
