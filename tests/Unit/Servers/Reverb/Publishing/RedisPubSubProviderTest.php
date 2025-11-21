<?php

use Clue\React\Redis\Client;
use Laravel\Reverb\Exceptions\RedisConnectionException;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisClientFactory;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisPubSubProvider;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Promise\Promise;

afterAll(function () {
    $loop = (new ReflectionClass(Loop::class));
    $property = $loop->getProperty('instance');

    if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(true);
    }

    $property->setValue($loop, null);
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
    $provider->disconnect();
})->throws(RedisConnectionException::class, 'Failed to connect to Redis connection [publisher] after retrying for 1s.');

it('queues publish events', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);

    $clientFactory->shouldReceive('make')
        ->twice()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect(Mockery::mock(LoopInterface::class));
    $provider->publish(['event' => 'first test']);
    $provider->publish(['event' => 'second test']);

    $publisher = (new ReflectionProperty($provider, 'publisher'))->getValue($provider);
    $queuedEvents = (new ReflectionProperty($publisher, 'queuedEvents'))->getValue($publisher);

    expect($queuedEvents)->toBe([['event' => 'first test'], ['event' => 'second test']]);
});

it('can process queued publish events', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);
    $client = Mockery::mock(Client::class);

    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve));

    $clientFactory->shouldReceive('make')
        ->once()
        ->andReturn(new Promise(fn (callable $resolve) => $resolve($client)));

    $client->shouldReceive('on')
        ->with('close', Mockery::any())
        ->once();

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect($loop = Mockery::mock(LoopInterface::class));
    $provider->publish(['event' => 'first test']);
    $provider->publish(['event' => 'second test']);

    $publisher = (new ReflectionProperty($provider, 'publisher'))->getValue($provider);
    $queuedEvents = (new ReflectionProperty($publisher, 'queuedEvents'))->getValue($publisher);

    expect($queuedEvents)->toHaveCount(2);
    collect($queuedEvents)->each(function ($event) use ($client) {
        $client->shouldReceive('publish')
            ->with('reverb', json_encode($event))
            ->once()
            ->andReturn(new Promise(fn () => null));
    });

    $publisher->connect($loop);
});

it('does not attempt to reconnect after a controlled disconnection', function () {
    $clientFactory = Mockery::mock(RedisClientFactory::class);
    $loop = Loop::get();

    // Publisher client
    $clientFactory->shouldReceive('make')
        ->twice()
        ->andReturn(new Promise(fn (callable $resolve) => throw new Exception));

    $provider = new RedisPubSubProvider($clientFactory, Mockery::mock(PubSubIncomingMessageHandler::class), 'reverb');
    $loop->addTimer(1, fn () => $provider->disconnect());
    $provider->connect($loop);
});
