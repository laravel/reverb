<?php

use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\MetricsHandler;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use React\EventLoop\Loop;
use React\Promise\Deferred;

afterEach(function () {
    $loop = (new ReflectionClass(Loop::class));
    $property = $loop->getProperty('instance');

    if (PHP_VERSION_ID < 80100) {
        $property->setAccessible(true);
    }

    $property->setValue($loop, null);
});

/**
 * @see https://github.com/laravel/reverb/issues/331
 */
it('removes the listener after metrics are gathered successfully', function () {
    $app = app(ApplicationProvider::class)->findByKey('reverb-key');
    app(ServerProviderManager::class)->withPublishing();

    $stopListeningCalled = false;
    $stopListeningKey = null;
    $registeredEvent = null;

    $pubSub = Mockery::mock(PubSubProvider::class);

    $pubSub->shouldReceive('on')
        ->once()
        ->with(Mockery::on(fn ($event) => str_starts_with($event, 'metrics-retrieved-')), Mockery::type('callable'))
        ->andReturnUsing(function ($event, $listener) use (&$registeredListener, &$registeredEvent) {
            $registeredListener = $listener;
            $registeredEvent = $event;
        });

    $pubSub->shouldReceive('publish')
        ->once()
        ->andReturnUsing(function ($payload) use (&$registeredListener) {
            $key = $payload['key'];

            Loop::addTimer(0.001, function () use (&$registeredListener, $key) {
                if ($registeredListener) {
                    $registeredListener([
                        'key' => $key,
                        'payload' => ['connections' => []],
                    ]);
                }
            });

            $deferred = new Deferred;
            $deferred->resolve(1);

            return $deferred->promise();
        });

    $pubSub->shouldReceive('stopListeningForMetrics')
        ->with(Mockery::on(function ($key) use (&$stopListeningCalled, &$stopListeningKey, &$registeredEvent) {
            $stopListeningCalled = true;
            $stopListeningKey = $key;

            return $registeredEvent === "metrics-retrieved-{$key}";
        }));

    $this->app->instance(PubSubProvider::class, $pubSub);

    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        $pubSub
    );

    $reflection = new ReflectionClass($handler);
    $gatherMethod = $reflection->getMethod('gatherMetricsFromSubscribers');
    $gatherMethod->setAccessible(true);

    $gatherMethod->invoke($handler, $app, 'connections', []);

    Loop::addTimer(0.1, fn () => Loop::stop());
    Loop::run();

    expect($stopListeningCalled)->toBeTrue();
    expect($registeredEvent)->toBe("metrics-retrieved-{$stopListeningKey}");
});
