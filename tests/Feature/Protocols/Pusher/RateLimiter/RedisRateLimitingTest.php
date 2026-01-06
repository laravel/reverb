<?php

use Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException;
use Laravel\Reverb\RateLimiting\RateLimitManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Tests\FakeConnection;
use Laravel\Reverb\Tests\ReverbTestCase;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

uses(ReverbTestCase::class);

beforeEach(function () {
    $mockPubSubProvider = Mockery::mock(PubSubProvider::class);
    $mockPubSubProvider->shouldReceive('connect')->andReturn(null);
    $mockPubSubProvider->shouldReceive('subscribe')->andReturn(null);
    $mockPubSubProvider->shouldReceive('disconnect')->andReturn(null);
    $mockPubSubProvider->shouldReceive('on')->andReturn(null);
    $mockPubSubProvider->shouldReceive('publish')->andReturn(\React\Promise\resolve(true));
    
    $this->app->instance(PubSubProvider::class, $mockPubSubProvider);
    
    config([
        'reverb.rate_limiting.enabled' => true,
        'reverb.rate_limiting.max_attempts' => 2,
        'reverb.rate_limiting.decay_seconds' => 10,
        'reverb.rate_limiting.terminate_on_limit' => false,
    ]);
});

it('blocks messages after exceeding rate limit without terminating', function () {
    config(['reverb.rate_limiting.terminate_on_limit' => false]);
    
    $rateLimitManager = Mockery::mock(RateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->twice()
        ->andReturn(null);
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andThrow(new RateLimitExceededException());
    
    $this->app->instance(RateLimitManager::class, $rateLimitManager);
    
    $connection = new FakeConnection();
    $server = $this->app->make('Laravel\Reverb\Protocols\Pusher\Server');
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'First message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Second message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Third message'],
    ]));
    
    $found = false;
    foreach ($connection->messages as $message) {
        $decoded = json_decode($message, true);
        if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
            $data = json_decode($decoded['data'], true);
            if (isset($data['code']) && $data['code'] === HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS) {
                $found = true;
                break;
            }
        }
    }
    
    expect($found)->toBeTrue('No rate limit error message was found');
    
    expect($connection->wasTerminated)->toBeFalse();
});

it('terminates connection after exceeding rate limit when configured', function () {
    config(['reverb.rate_limiting.terminate_on_limit' => true]);
    
    $rateLimitManager = Mockery::mock(RateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->twice()
        ->andReturn(null);
    
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andReturnUsing(function($connection) {
            $connection->terminate();
            throw new RateLimitExceededException();
        });
    
    $this->app->instance(RateLimitManager::class, $rateLimitManager);
    
    $connection = new FakeConnection();
    $server = $this->app->make('Laravel\Reverb\Protocols\Pusher\Server');
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'First message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Second message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Third message'],
    ]));
    
    $found = false;
    foreach ($connection->messages as $message) {
        $decoded = json_decode($message, true);
        if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
            $data = json_decode($decoded['data'], true);
            if (isset($data['code']) && $data['code'] === HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS) {
                $found = true;
                break;
            }
        }
    }
    
    expect($found)->toBeTrue('No rate limit error message was found');
    
    expect($connection->wasTerminated)->toBeTrue();
});

it('allows messages after rate limit window expires', function () {
    $rateLimitManager = Mockery::mock(RateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->twice()
        ->andReturn(null);
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andThrow(new RateLimitExceededException());
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andReturn(null);
    
    $this->app->instance(RateLimitManager::class, $rateLimitManager);
    
    $connection = new FakeConnection();
    $server = $this->app->make('Laravel\Reverb\Protocols\Pusher\Server');
    
    config([
        'reverb.rate_limiting.decay_seconds' => 2,
        'reverb.rate_limiting.terminate_on_limit' => false,
    ]);
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'First message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Second message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Third message (blocked)'],
    ]));
    
    sleep(3);
    
    $messageCountBefore = count($connection->messages);
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Fourth message (allowed)'],
    ]));
    
    $errorCount = 0;
    foreach ($connection->messages as $message) {
        $decoded = json_decode($message, true);
        if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
            $data = json_decode($decoded['data'], true);
            if (isset($data['code']) && $data['code'] === HttpFoundationResponse::HTTP_TOO_MANY_REQUESTS) {
                $errorCount++;
            }
        }
    }
    
    expect($errorCount)->toBe(1);
    expect(count($connection->messages))->toBeGreaterThan($messageCountBefore);
    expect($connection->wasTerminated)->toBeFalse();
}); 