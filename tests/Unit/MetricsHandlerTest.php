<?php

use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Protocols\Pusher\MetricsHandler;
use Laravel\Reverb\Protocols\Pusher\MetricType;
use Laravel\Reverb\Protocols\Pusher\PendingMetric;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Tests\FakeConnection;
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
    $metric = new PendingMetric('test', $app, MetricType::CONNECTIONS);

    $stopListeningCalled = false;
    $stopListeningKey = null;
    $registeredEvent = null;

    $pubSub = Double::for(PubSubProvider::class);

    $pubSub->expects('on')->with(Argument::satisfies(fn ($event) => str_starts_with($event, 'test')), Argument::type('callable'))->resolves(function ($event, $listener) use (&$registeredListener, &$registeredEvent) {
        $registeredListener = $listener;
        $registeredEvent = $event;
    });

    $pubSub->expects('publish')->resolves(function ($payload) use (&$registeredListener) {

        Loop::addTimer(0.001, function () use (&$registeredListener) {
            if ($registeredListener) {
                $registeredListener([
                    'key' => 'test',
                    'payload' => ['connections' => []],
                ]);
            }
        });

        $deferred = new Deferred;
        $deferred->resolve(1);

        return $deferred->promise();
    });

    $pubSub->expects('stopListening')->with(Argument::satisfies(function ($key) use (&$stopListeningCalled, &$stopListeningKey, &$registeredEvent) {
        $stopListeningCalled = true;
        $stopListeningKey = $key;

        return $registeredEvent === $key;
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

    $gatherMethod->invoke($handler, $metric);

    expect($reflection->getProperty('metrics')->getValue($handler))->toHaveKey('test');

    Loop::addTimer(0.1, fn () => Loop::stop());
    Loop::run();

    expect($stopListeningCalled)->toBeTrue();
    expect($registeredEvent)->toBe($stopListeningKey);
    expect($reflection->getProperty('metrics')->getValue($handler))->not->toHaveKey('test');
});

it('gathers presence data from all subscribers', function () {
    $app = app(ApplicationProvider::class)->findByKey('reverb-key');
    app(ServerProviderManager::class)->withPublishing();
    $metric = new PendingMetric('test', $app, MetricType::PRESENCE_DATA, ['channel' => 'presence-test-channel']);

    $registeredListener = null;
    $gathered = null;

    $pubSub = Double::for(PubSubProvider::class);

    $pubSub->expects('on')->resolves(function ($event, $listener) use (&$registeredListener) {
        $registeredListener = $listener;
    });

    $pubSub->expects('publish')->resolves(function ($payload) use (&$registeredListener) {
        Loop::addTimer(0.001, function () use (&$registeredListener) {
            $registeredListener(['key' => 'test', 'payload' => ['presence' => [
                'count' => 2,
                'ids' => [1, 2],
                'hash' => [1 => ['name' => 'Joe'], 2 => ['name' => 'Jane']],
            ]]]);

            $registeredListener(['key' => 'test', 'payload' => ['presence' => [
                'count' => 2,
                'ids' => ['2', 3, 4],
                'hash' => ['2' => ['name' => 'Jane'], 3 => ['name' => 'Jim'], 4 => []],
            ]]]);

            $registeredListener(['key' => 'test', 'payload' => []]);
        });

        $deferred = new Deferred;
        $deferred->resolve(3);

        return $deferred->promise();
    });

    $this->app->instance(PubSubProvider::class, $pubSub);

    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        $pubSub
    );

    $gatherMethod = (new ReflectionClass($handler))->getMethod('gatherMetricsFromSubscribers');
    $gatherMethod->setAccessible(true);

    $gatherMethod->invoke($handler, $metric)->then(function ($metrics) use (&$gathered) {
        $gathered = $metrics;
    });

    Loop::addTimer(0.1, fn () => Loop::stop());
    Loop::run();

    expect(json_encode($gathered))->toBe(json_encode([
        'presence' => [
            'count' => 4,
            'ids' => [1, 2, 3, 4],
            'hash' => [
                1 => ['name' => 'Joe'],
                2 => ['name' => 'Jane'],
                3 => ['name' => 'Jim'],
                4 => (object) [],
            ],
        ],
    ]));
});

it('gathers empty presence data for an unknown channel', function () {
    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        Double::for(PubSubProvider::class)
    );

    $mergeMethod = (new ReflectionClass($handler))->getMethod('mergePresenceData');
    $mergeMethod->setAccessible(true);

    expect($mergeMethod->invoke($handler, [[], []]))->toBe([
        'presence' => [
            'count' => 0,
            'ids' => [],
            'hash' => [],
        ],
    ]);
});

it('gets the connections a user holds on a presence channel', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $first = new FakeConnection;
    $second = new FakeConnection;
    $other = new FakeConnection;

    foreach ([[$first, 1], [$second, 1], [$other, 2]] as [$connection, $userId]) {
        $data = json_encode(['user_id' => $userId]);
        $channel->subscribe($connection, validAuth($connection->id(), 'presence-test-channel', $data), $data);
    }

    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        Double::for(PubSubProvider::class)
    );

    $connections = $handler->get(new PendingMetric('test', $first->app(), MetricType::PRESENCE_CONNECTIONS, [
        'channel' => 'presence-test-channel',
        'user_id' => '1',
    ]));

    expect($connections)->toHaveCount(2);
    expect(collect($connections)->pluck('id')->all())->toBe([$first->id(), $second->id()]);
    expect($connections[0]['subscribed_at'])->toBeFloat();
});

it('gets no presence connections for an unknown channel', function () {
    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        Double::for(PubSubProvider::class)
    );

    $connections = $handler->get(new PendingMetric('test', app(ApplicationProvider::class)->findByKey('reverb-key'), MetricType::PRESENCE_CONNECTIONS, [
        'channel' => 'presence-test-channel',
        'user_id' => '1',
    ]));

    expect($connections)->toBe([]);
});

it('merges presence connections from all subscribers', function () {
    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        Double::for(PubSubProvider::class)
    );

    $mergeMethod = (new ReflectionClass($handler))->getMethod('mergeSubscriberMetrics');
    $mergeMethod->setAccessible(true);

    $merged = $mergeMethod->invoke($handler, [
        [['id' => 'socket-one', 'subscribed_at' => 100.0]],
        [],
        [
            ['id' => 'socket-two', 'subscribed_at' => 100.5],
            ['id' => 'socket-three', 'subscribed_at' => 101.0],
        ],
    ], MetricType::PRESENCE_CONNECTIONS);

    expect($merged)->toBe([
        ['id' => 'socket-one', 'subscribed_at' => 100.0],
        ['id' => 'socket-two', 'subscribed_at' => 100.5],
        ['id' => 'socket-three', 'subscribed_at' => 101.0],
    ]);
});

it('merges channel users from all subscribers into a list', function () {
    $handler = new MetricsHandler(
        app(ServerProviderManager::class),
        app(ChannelManager::class),
        Double::for(PubSubProvider::class)
    );

    $mergeMethod = (new ReflectionClass($handler))->getMethod('mergeSubscriberMetrics');
    $mergeMethod->setAccessible(true);

    $merged = $mergeMethod->invoke($handler, [
        [['id' => 1], ['id' => 2]],
        [['id' => 2], ['id' => 3]],
    ], MetricType::CHANNEL_USERS);

    expect($merged)->toBe([['id' => 1], ['id' => 2], ['id' => 3]]);
});
