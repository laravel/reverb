<?php

use JMac\Testing\Matching\Argument;
use JMac\Testing\Double;
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
    $clientFactory = Double::for(RedisClientFactory::class);
    $loop = Double::for(LoopInterface::class);

    $loop->expects('addTimer')->with(1, Argument::any());

    // Publisher client, then subscriber client
    $clientFactory->expects('make')->times(2)->returns(
        new Promise(fn () => throw new Exception),
        new Promise(fn (callable $resolve) => $resolve),
    );

    $provider = new RedisPubSubProvider($clientFactory, Double::for(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect($loop);
});

it('can timeout and fail when unable to reconnect', function () {
    $clientFactory = Double::for(RedisClientFactory::class);

    $loop = Loop::get();

    // Publisher client, then subscriber client
    $clientFactory->expects('make')->times(2)->returns(
        new Promise(fn () => throw new Exception),
        new Promise(fn (callable $resolve) => $resolve),
    );

    $provider = new RedisPubSubProvider($clientFactory, Double::for(PubSubIncomingMessageHandler::class), 'reverb', ['host' => 'localhost', 'port' => 6379, 'timeout' => 1]);
    $provider->connect($loop);
    $loop->run();
    $provider->disconnect();
})->throws(RedisConnectionException::class, 'Failed to connect to Redis connection [publisher] after retrying for 1s.');

it('queues publish events', function () {
    $clientFactory = Double::for(RedisClientFactory::class);

    $clientFactory->expects('make')->times(2)->returns(new Promise(fn (callable $resolve) => $resolve));

    $provider = new RedisPubSubProvider($clientFactory, Double::for(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect(Double::for(LoopInterface::class));
    $provider->publish(['event' => 'first test']);
    $provider->publish(['event' => 'second test']);

    $publisher = (new ReflectionProperty($provider, 'publisher'))->getValue($provider);
    $queuedEvents = (new ReflectionProperty($publisher, 'queuedEvents'))->getValue($publisher);

    expect($queuedEvents)->toBe([['event' => 'first test'], ['event' => 'second test']]);
});

it('can process queued publish events', function () {
    $clientFactory = Double::for(RedisClientFactory::class);
    $client = Double::for(Client::class);

    $clientFactory->expects('make')->returns(new Promise(fn (callable $resolve) => $resolve));

    $clientFactory->expects('make')->returns(new Promise(fn (callable $resolve) => $resolve));

    $clientFactory->expects('make')->returns(new Promise(fn (callable $resolve) => $resolve($client)));

    $client->expects('on')->with('close', Argument::any());

    $provider = new RedisPubSubProvider($clientFactory, Double::for(PubSubIncomingMessageHandler::class), 'reverb');
    $provider->connect($loop = Double::for(LoopInterface::class));
    $provider->publish(['event' => 'first test']);
    $provider->publish(['event' => 'second test']);

    $publisher = (new ReflectionProperty($provider, 'publisher'))->getValue($provider);
    $queuedEvents = (new ReflectionProperty($publisher, 'queuedEvents'))->getValue($publisher);

    expect($queuedEvents)->toHaveCount(2);
    collect($queuedEvents)->each(function ($event) use ($client) {
        $client->expects('publish')->with('reverb', json_encode($event))->returns(new Promise(fn () => null));
    });

    $publisher->connect($loop);
});

it('does not attempt to reconnect after a controlled disconnection', function () {
    $clientFactory = Double::for(RedisClientFactory::class);
    $loop = Loop::get();

    // Publisher client
    $clientFactory->expects('make')->times(2)->returns(new Promise(fn (callable $resolve) => throw new Exception));

    $provider = new RedisPubSubProvider($clientFactory, Double::for(PubSubIncomingMessageHandler::class), 'reverb');
    $loop->addTimer(1, fn () => $provider->disconnect());
    $provider->connect($loop);
});
