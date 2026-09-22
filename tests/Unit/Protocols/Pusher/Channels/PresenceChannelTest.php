<?php

use JMac\Testing\Double;
use JMac\Testing\Matching\Argument;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Channels\PresenceChannel;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelConnectionManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\ConnectionUnauthorized;
use Laravel\Reverb\Protocols\Pusher\MetricsHandler;
use Laravel\Reverb\ServerProviderManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Tests\FakeConnection;

use function React\Promise\reject;
use function React\Promise\resolve;

beforeEach(function () {
    $this->connection = new FakeConnection;
    $this->channelConnectionManager = Double::for(ChannelConnectionManager::class);
    $this->channelConnectionManager->expects('for')->returns($this->channelConnectionManager);
    $this->app->instance(ChannelConnectionManager::class, $this->channelConnectionManager);
});

it('can subscribe a connection to a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->expects('add');

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel'));
});

it('can unsubscribe a connection from a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->expects('remove')->with($this->connection);

    $channel->unsubscribe($this->connection);
});

it('can broadcast to all connections of a channel', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $channel->broadcast(['foo' => 'bar']);

    collect($connections)->each(fn ($connection) => $connection->assertReceived(['foo' => 'bar']));
});

it('fails to subscribe if the signature is invalid', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $this->channelConnectionManager->expects('add')->never();

    $channel->subscribe($this->connection, 'invalid-signature');
})->throws(ConnectionUnauthorized::class);

it('can return data stored on the connection', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connections = [
        collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first(),
        collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 2]))->first(),
    ];

    $this->channelConnectionManager->expects('all')->returns($connections);

    expect($channel->data($this->connection->app()))->toBe([
        'presence' => [
            'count' => 2,
            'ids' => [1, 2],
            'hash' => [
                1 => ['name' => 'Joe'],
                2 => ['name' => 'Joe'],
            ],
        ],
    ]);
});

it('can return data for a connection without user info', function () {
    $channel = new PresenceChannel('presence-test-channel');

    $connections = [
        collect(factory(data: ['user_id' => 1]))->first(),
    ];

    $this->channelConnectionManager->expects('all')->returns($connections);

    expect(json_encode($channel->data($this->connection->app())))->toBe(
        '{"presence":{"count":1,"ids":[1],"hash":{"1":{}}}}'
    );
});

it('sends notification of subscription', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $this->channelConnectionManager->expects('add')->with($this->connection, []);

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel'));

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_added',
        'data' => '{}',
        'channel' => 'presence-test-channel',
    ]));
});

it('sends notification of subscription with data', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $data = json_encode(['name' => 'Joe']);

    $this->channelConnectionManager->expects('add')->with($this->connection, ['name' => 'Joe']);

    $this->channelConnectionManager->expects('all')->returns($connections = factory(3));

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-test-channel',
            $data
        ),
        $data
    );

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_added',
        'data' => json_encode(['name' => 'Joe']),
        'channel' => 'presence-test-channel',
    ]));
});

it('sends notification of an unsubscribe', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $data = json_encode(['user_info' => ['name' => 'Joe'], 'user_id' => 1]);

    $channel->subscribe(
        $this->connection,
        validAuth(
            $this->connection->id(),
            'presence-test-channel',
            $data
        ),
        $data
    );

    $this->channelConnectionManager->expects('find')->returns(new ChannelConnection($this->connection, ['user_info' => ['name' => 'Joe'], 'user_id' => 1]));

    $this->channelConnectionManager->expects('all')->times(2)->returns($connections = factory(3));

    $this->channelConnectionManager->expects('remove')->with($this->connection);

    $channel->unsubscribe($this->connection);

    collect($connections)->each(fn ($connection) => $connection->assertReceived([
        'event' => 'pusher_internal:member_removed',
        'data' => json_encode(['user_id' => 1]),
        'channel' => 'presence-test-channel',
    ]));
});

it('ensures the "member_added" event is only fired once', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->expects('all')->times(2)->returns([$connectionOne, $connectionTwo]);

    $channel->subscribe($connectionOne->connection(), validAuth($connectionOne->id(), 'presence-test-channel', $data = json_encode($connectionOne->data())), $data);
    $channel->subscribe($connectionTwo->connection(), validAuth($connectionTwo->id(), 'presence-test-channel', $data = json_encode($connectionTwo->data())), $data);

    $connectionOne->connection()->assertNothingReceived();
});

it('ensures the "member_removed" event is only fired once', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $connectionOne = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();
    $connectionTwo = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->expects('find')->returns($connectionOne);

    $this->channelConnectionManager->expects('all')->returns([$connectionOne, $connectionTwo]);

    $channel->unsubscribe($connectionTwo->connection(), validAuth($connectionTwo->id(), 'presence-test-channel', $data = json_encode($connectionTwo->data())), $data);

    $connectionOne->connection()->assertNothingReceived();
});

it('can publish presence member events when scaling', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $published = [];

    $provider = Double::for(PubSubProvider::class);
    $provider->expects('publish')->with(Argument::satisfies(function ($payload) use (&$published) {
        $published[] = $payload;

        return true;
    }));

    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $this->channelConnectionManager->expects('add')->with($this->connection, []);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel'));

    expect($published)->toHaveCount(1);
    expect($published[0]['type'])->toBe('message');
    expect($published[0]['socket_id'])->toBe($this->connection->id());
    expect($published[0]['payload'])->toBe([
        'event' => 'pusher_internal:member_added',
        'data' => '{}',
        'channel' => 'presence-test-channel',
    ]);
});

it('does not send member_removed when the user is connected elsewhere', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $provider = Double::for(PubSubProvider::class);
    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(resolve([
        ['id' => 'socket-on-another-server', 'subscribed_at' => 100.0],
    ]));
    $this->app->instance(MetricsHandler::class, $metrics);

    $connection = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->expects('find')->returns($connection);
    $this->channelConnectionManager->expects('remove');
    $this->channelConnectionManager->expects('all')->returns([]);

    $channel->unsubscribe($connection->connection());

    $provider->received('publish')->never();
    $connection->connection()->assertNothingReceived();
});

it('sends member_removed when the last connection for the user leaves', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $published = [];

    $provider = Double::for(PubSubProvider::class);
    $provider->expects('publish')->with(Argument::satisfies(function ($payload) use (&$published) {
        $published[] = $payload;

        return true;
    }));

    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(resolve([]));
    $this->app->instance(MetricsHandler::class, $metrics);

    $connection = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->expects('find')->returns($connection);
    $this->channelConnectionManager->expects('remove');
    $this->channelConnectionManager->expects('all')->returns([]);

    $channel->unsubscribe($connection->connection());

    expect($published)->toHaveCount(1);
    expect($published[0]['payload'])->toBe([
        'event' => 'pusher_internal:member_removed',
        'data' => json_encode(['user_id' => 1]),
        'channel' => 'presence-test-channel',
    ]);
});

it('sends member_added for the user\'s first connection', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $published = [];

    $provider = Double::for(PubSubProvider::class);
    $provider->expects('publish')->with(Argument::satisfies(function ($payload) use (&$published) {
        $published[] = $payload;

        return true;
    }));

    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(resolve([
        ['id' => $this->connection->id(), 'subscribed_at' => 100.0],
    ]));
    $this->app->instance(MetricsHandler::class, $metrics);

    $this->channelConnectionManager->expects('all')->returns([]);
    $this->channelConnectionManager->expects('add');

    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel', $data), $data);

    expect($published)->toHaveCount(1);
    expect($published[0]['socket_id'])->toBe($this->connection->id());
    expect($published[0]['payload'])->toBe([
        'event' => 'pusher_internal:member_added',
        'data' => $data,
        'channel' => 'presence-test-channel',
    ]);
});

it('does not send member_added when the user is connected elsewhere', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $provider = Double::for(PubSubProvider::class);
    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(resolve([
        ['id' => 'socket-on-another-server', 'subscribed_at' => 100.0],
        ['id' => $this->connection->id(), 'subscribed_at' => 100.5],
    ]));
    $this->app->instance(MetricsHandler::class, $metrics);

    $this->channelConnectionManager->expects('all')->returns([]);
    $this->channelConnectionManager->expects('add');

    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel', $data), $data);

    $provider->received('publish')->never();
});

it('uses the socket ID to decide between connections made at the same time', function () {
    $channel = channels()->findOrCreate('presence-test-channel');

    $provider = Double::for(PubSubProvider::class);
    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $this->connection->id = 'socket-two';

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(resolve([
        ['id' => 'socket-two', 'subscribed_at' => 100.0],
        ['id' => 'socket-one', 'subscribed_at' => 100.0],
    ]));
    $this->app->instance(MetricsHandler::class, $metrics);

    $this->channelConnectionManager->expects('all')->returns([]);
    $this->channelConnectionManager->expects('add');

    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel', $data), $data);

    $provider->received('publish')->never();
});

it('sends member_added when no connections are gathered', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $published = [];

    $provider = Double::for(PubSubProvider::class);
    $provider->expects('publish')->with(Argument::satisfies(function ($payload) use (&$published) {
        $published[] = $payload;

        return true;
    }));

    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(resolve([]));
    $this->app->instance(MetricsHandler::class, $metrics);

    $this->channelConnectionManager->expects('all')->returns([]);
    $this->channelConnectionManager->expects('add');

    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel', $data), $data);

    expect($published)->toHaveCount(1);
    expect($published[0]['payload']['event'])->toBe('pusher_internal:member_added');
});

it('sends member_added when the gather fails', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $published = [];

    $provider = Double::for(PubSubProvider::class);
    $provider->expects('publish')->with(Argument::satisfies(function ($payload) use (&$published) {
        $published[] = $payload;

        return true;
    }));

    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(reject(new Exception('Unable to gather metrics.')));
    $this->app->instance(MetricsHandler::class, $metrics);

    $this->channelConnectionManager->expects('all')->returns([]);
    $this->channelConnectionManager->expects('add');

    $data = json_encode(['user_id' => 1, 'user_info' => ['name' => 'Joe']]);

    $channel->subscribe($this->connection, validAuth($this->connection->id(), 'presence-test-channel', $data), $data);

    expect($published)->toHaveCount(1);
    expect($published[0]['payload']['event'])->toBe('pusher_internal:member_added');
});

it('sends member_removed when the gather fails', function () {
    $channel = channels()->findOrCreate('presence-test-channel');
    $published = [];

    $provider = Double::for(PubSubProvider::class);
    $provider->expects('publish')->with(Argument::satisfies(function ($payload) use (&$published) {
        $published[] = $payload;

        return true;
    }));

    $this->app->instance(PubSubProvider::class, $provider);
    app(ServerProviderManager::class)->withPublishing();

    $metrics = Double::for(MetricsHandler::class);
    $metrics->expects('gather')->returns(reject(new Exception('Unable to gather metrics.')));
    $this->app->instance(MetricsHandler::class, $metrics);

    $connection = collect(factory(data: ['user_info' => ['name' => 'Joe'], 'user_id' => 1]))->first();

    $this->channelConnectionManager->expects('find')->returns($connection);
    $this->channelConnectionManager->expects('remove');
    $this->channelConnectionManager->expects('all')->returns([]);

    $channel->unsubscribe($connection->connection());

    expect($published)->toHaveCount(1);
    expect($published[0]['payload']['event'])->toBe('pusher_internal:member_removed');
});
