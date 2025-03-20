<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\RateLimiting\RateLimitManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException;
use Laravel\Reverb\Tests\FakeConnection;
use Laravel\Reverb\Tests\ReverbTestCase;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

uses(ReverbTestCase::class);

beforeEach(function () {
    Config::set('reverb.rate_limiting.enabled', true);
    Config::set('reverb.rate_limiting.max_attempts', 10);
    Config::set('reverb.rate_limiting.decay_seconds', 10);
});

it('allows messages when rate limiting is disabled', function () {
    Config::set('reverb.rate_limiting.enabled', false);
    
    $rateLimitManager = Mockery::mock(RateLimitManager::class);
    $rateLimitManager->shouldNotReceive('handle');
    
    $this->app->instance(RateLimitManager::class, $rateLimitManager);

    $connection = new FakeConnection();
    $this->app->make('Laravel\Reverb\Protocols\Pusher\Server')->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Hello World'],
    ]));
});

it('rate limits messages when enabled', function () {
    $rateLimitManager = Mockery::mock(RateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andReturn(null);
    
    $this->app->instance(RateLimitManager::class, $rateLimitManager);

    $connection = new FakeConnection();
    $this->app->make('Laravel\Reverb\Protocols\Pusher\Server')->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Hello World'],
    ]));
});

it('blocks messages when over the rate limit', function () {
    $rateLimitManager = Mockery::mock(RateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andThrow(new RateLimitExceededException());
    
    $this->app->instance(RateLimitManager::class, $rateLimitManager);

    $connection = new FakeConnection();
    $this->app->make('Laravel\Reverb\Protocols\Pusher\Server')->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Hello World'],
    ]));

    $connection->assertReceived([
        'event' => 'pusher:error',
        'data' => json_encode([
            'code' => HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS,
            'message' => 'Too Many Requests',
        ]),
    ]);
});

it('uses correct configuration values', function () {
    Config::set('reverb.rate_limiting.max_attempts', 5);
    Config::set('reverb.rate_limiting.decay_seconds', 20);

    $realRateLimiter = app(RateLimiter::class);
    $realManager = new RateLimitManager(
        $realRateLimiter,
        Config::get('reverb.rate_limiting.max_attempts'),
        Config::get('reverb.rate_limiting.decay_seconds')
    );
    
    expect($realManager->getMaxAttempts())->toBe(5);
    expect($realManager->getDecaySeconds())->toBe(20);
});