<?php

use Clue\React\Redis\Client;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisClientFactory;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisPubSubProvider;
use React\EventLoop\Loop;
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

it('can successfully reconnect', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);
    $loop = Mockery::mock(LoopInterface::class);

    $loop->shouldReceive('addTimer')
        ->once()
        ->with(1, Mockery::any());

    // Publisher client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn () => throw new Exception));

    // Subscriber client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect($loop);
});

it('can timeout and fail when unable to reconnect', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);
    $loop = Loop::get();

    // Publisher client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn () => throw new Exception));

    // Subscriber client
    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb', ['host' => 'localhost', 'port' => 6379, 'timeout' => 1]);
    $provider->connect($loop);

    $loop->run();
})->throws(Exception::class, 'Failed to reconnect to Redis connection [publisher] within 1 second limit');

it('queues subscription events', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);

    $clientFactory->shouldReceive('make')
        ->twice()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect(Mockery::mock(LoopInterface::class));
    $provider->subscribe();

    $subscribingClient = (new ReflectionProperty($provider, 'subscribingClient'))->getValue($provider);
    $queuedSubscriptionEvents = (new ReflectionProperty($subscribingClient, 'queuedSubscriptionEvents'))->getValue($subscribingClient);

    expect(array_keys($queuedSubscriptionEvents))->toBe(['subscribe', 'on']);
});

it('can process queued subscription events', function () {})->todo();

it('queues publish events', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);

    $clientFactory->shouldReceive('make')
        ->twice()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect(Mockery::mock(LoopInterface::class));
    $provider->publish(['event' => 'first test']);
    $provider->publish(['event' => 'second test']);

    $publishingClient = (new ReflectionProperty($provider, 'publishingClient'))->getValue($provider);
    $queuedPublishEvents = (new ReflectionProperty($publishingClient, 'queuedPublishEvents'))->getValue($publishingClient);

    expect($queuedPublishEvents)->toBe([['event' => 'first test'], ['event' => 'second test']]);
});

it('can process queued publish events', function () {})->todo();

it('does not attempt to reconnect after a controlled disconnection', function () {})->todo();
