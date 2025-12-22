<?php

use Laravel\Reverb\Servers\Reverb\Contracts\PubSubIncomingMessageHandler;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisClientFactory;
use Laravel\Reverb\Servers\Reverb\Publishing\RedisPubSubProvider;

/**
 * @see https://github.com/laravel/reverb/issues/331
 */
it('removes listeners from the subscriber when off() is called', function () {
    $subscriberListenerCount = 0;

    $mockSubscriber = new class($subscriberListenerCount) {
        private int $listenerCount;

        public function __construct(int &$count)
        {
            $this->listenerCount = &$count;
        }

        public function on(string $event, callable $callback): void
        {
            $this->listenerCount++;
        }

        public function removeListener(string $event, callable $callback): void
        {
            $this->listenerCount--;
        }

        public function getListenerCount(): int
        {
            return $this->listenerCount;
        }
    };

    $provider = new RedisPubSubProvider(
        Mockery::mock(RedisClientFactory::class),
        Mockery::mock(PubSubIncomingMessageHandler::class),
        'reverb',
        []
    );

    $reflection = new ReflectionClass($provider);
    $property = $reflection->getProperty('subscriber');
    $property->setAccessible(true);
    $property->setValue($provider, $mockSubscriber);

    for ($i = 0; $i < 100; $i++) {
        $callback = function ($payload) {};
        $provider->on('metrics-retrieved', $callback);
        $provider->off('metrics-retrieved', $callback);
    }

    expect($mockSubscriber->getListenerCount())->toBe(0);
});
