<?php

use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisClientFactory;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisPubSubProvider;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisSubscribeClient;

/**
 * @see https://github.com/laravel/reverb/issues/331
 */
it('removes all listeners for an event when stopListening is called', function () {
    $provider = new RedisPubSubProvider(
        Mockery::mock(RedisClientFactory::class),
        $handler = Mockery::mock(PubSubIncomingMessageHandler::class),
        'reverb',
        []
    );

    $handler->shouldReceive('listening')->times(100);
    $subscriber = Mockery::mock(RedisSubscribeClient::class);
    $subscriber->shouldReceive('listen')->times(100);
    $subscriber->shouldReceive('removeListener')->times(100);

    $reflection = new ReflectionClass($provider);
    $subscriberProperty = $reflection->getProperty('subscriber');
    $subscriberProperty->setAccessible(true);
    $subscriberProperty->setValue($provider, $subscriber);

    $keys = [];
    for ($i = 0; $i < 100; $i++) {
        $key = "key-{$i}";
        $keys[] = $key;
        $provider->on("metrics-retrieved-{$key}", function ($payload) {});
    }

    foreach ($keys as $key) {
        $provider->stopListening($key);
    }
})->skip();
