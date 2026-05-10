<?php

use Laravel\Reverb\Application;

// Getters

it('returns the application id', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000);

    expect($app->id())->toBe('app-1');
});

it('returns the application key', function () {
    $app = new Application('app-1', 'my-key', 'secret', 60, 30, [], 10000);

    expect($app->key())->toBe('my-key');
});

it('returns the application secret', function () {
    $app = new Application('app-1', 'key', 'my-secret', 60, 30, [], 10000);

    expect($app->secret())->toBe('my-secret');
});

it('returns the ping interval', function () {
    $app = new Application('app-1', 'key', 'secret', 120, 30, [], 10000);

    expect($app->pingInterval())->toBe(120);
});

it('returns the activity timeout', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 90, [], 10000);

    expect($app->activityTimeout())->toBe(90);
});

it('returns the allowed origins', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, ['example.com', 'foo.test'], 10000);

    expect($app->allowedOrigins())->toBe(['example.com', 'foo.test']);
});

it('returns the max message size', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 65536);

    expect($app->maxMessageSize())->toBe(65536);
});

it('returns the max connections', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, 500);

    expect($app->maxConnections())->toBe(500);
});

it('returns null for max connections when not set', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000);

    expect($app->maxConnections())->toBeNull();
});

it('returns the accept client events from setting', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, null, 'all');

    expect($app->acceptClientEventsFrom())->toBe('all');
});

it('defaults accept client events from to members', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000);

    expect($app->acceptClientEventsFrom())->toBe('members');
});

it('returns the rate limiting config', function () {
    $config = ['enabled' => true, 'limit' => 100];
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, null, 'members', $config);

    expect($app->rateLimiting())->toBe($config);
});

it('returns the options', function () {
    $options = ['tls' => true];
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, null, 'members', null, $options);

    expect($app->options())->toBe($options);
});

// Boolean helpers

it('has a max connection limit when max connections is set', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, 100);

    expect($app->hasMaxConnectionLimit())->toBeTrue();
});

it('does not have a max connection limit when max connections is null', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000);

    expect($app->hasMaxConnectionLimit())->toBeFalse();
});

it('uses rate limiting when enabled flag is true', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, null, 'members', ['enabled' => true]);

    expect($app->usesRateLimiting())->toBeTrue();
});

it('does not use rate limiting when enabled flag is false', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, null, 'members', ['enabled' => false]);

    expect($app->usesRateLimiting())->toBeFalse();
});

it('does not use rate limiting when rate limiting config is null', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000);

    expect($app->usesRateLimiting())->toBeFalse();
});

it('does not use rate limiting when enabled key is missing from config', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, null, 'members', ['limit' => 100]);

    expect($app->usesRateLimiting())->toBeFalse();
});

// Serialization

it('serializes to array with correct keys and values', function () {
    $app = new Application('app-1', 'my-key', 'my-secret', 60, 30, ['example.com'], 10000, null, 'members', null, ['tls' => true]);

    expect($app->toArray())->toBe([
        'app_id' => 'app-1',
        'key' => 'my-key',
        'secret' => 'my-secret',
        'ping_interval' => 60,
        'activity_timeout' => 30,
        'allowed_origins' => ['example.com'],
        'max_message_size' => 10000,
        'options' => ['tls' => true],
    ]);
});

it('toArray does not include max_connections or rate_limiting', function () {
    $app = new Application('app-1', 'key', 'secret', 60, 30, [], 10000, 500, 'members', ['enabled' => true]);

    $array = $app->toArray();

    expect($array)->not->toHaveKey('max_connections');
    expect($array)->not->toHaveKey('rate_limiting');
});
