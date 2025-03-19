<?php

use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException;
use Laravel\Reverb\RateLimiting\WebSocketRateLimitManager;
use Laravel\Reverb\Servers\Reverb\Contracts\PubSubProvider;
use Laravel\Reverb\Tests\FakeConnection;
use Laravel\Reverb\Tests\ReverbTestCase;
use Symfony\Component\HttpFoundation\Response as HttpFoundationResponse;

use function React\Async\await;

uses(ReverbTestCase::class);

beforeEach(function () {
    // Use a mock PubSubProvider instead of real Redis
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
    ]);
});

it('blocks messages after exceeding rate limit', function () {
    // Create a mock that throws rate limit exception on the third call
    $rateLimitManager = Mockery::mock(WebSocketRateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->twice()
        ->andReturn(null);
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andThrow(new RateLimitExceededException());
    
    $this->app->instance(WebSocketRateLimitManager::class, $rateLimitManager);
    
    $connection = new FakeConnection();
    $server = $this->app->make('Laravel\Reverb\Protocols\Pusher\Server');
    
    // First two messages should go through
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'First message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Second message'],
    ]));
    
    // Third message should be blocked
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Third message'],
    ]));
    
    // Find rate limit error
    $found = false;
    foreach ($connection->messages as $message) {
        $decoded = json_decode($message, true);
        if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
            $data = json_decode($decoded['data'], true);
            if (isset($data['code']) && $data['code'] === 429) {
                $found = true;
                break;
            }
        }
    }
    
    expect($found)->toBeTrue('No rate limit error message was found');
});

it('allows messages after rate limit window expires', function () {
    // Create a mock rate limit manager that throws an exception on the third message
    // but allows the fourth message after the sleep
    $rateLimitManager = Mockery::mock(WebSocketRateLimitManager::class);
    $rateLimitManager->shouldReceive('handle')
        ->twice()
        ->andReturn(null);
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andThrow(new RateLimitExceededException());
    $rateLimitManager->shouldReceive('handle')
        ->once()
        ->andReturn(null);
    
    $this->app->instance(WebSocketRateLimitManager::class, $rateLimitManager);
    
    $connection = new FakeConnection();
    $server = $this->app->make('Laravel\Reverb\Protocols\Pusher\Server');
    
    // Set a shorter decay period for testing
    config(['reverb.rate_limiting.decay_seconds' => 2]);
    
    // First two messages should go through
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'First message'],
    ]));
    
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Second message'],
    ]));
    
    // Third message should be blocked
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Third message (blocked)'],
    ]));
    
    // Wait for the rate limit to expire
    sleep(3);
    
    // Message count before sending the fourth message
    $messageCountBefore = count($connection->messages);
    
    // Fourth message should go through after rate limit expires
    $server->message($connection, json_encode([
        'event' => 'client-test-event',
        'data' => ['message' => 'Fourth message (allowed)'],
    ]));
    
    // Count error messages
    $errorCount = 0;
    foreach ($connection->messages as $message) {
        $decoded = json_decode($message, true);
        if (isset($decoded['event']) && $decoded['event'] === 'pusher:error') {
            $data = json_decode($decoded['data'], true);
            if (isset($data['code']) && $data['code'] === 429) {
                $errorCount++;
            }
        }
    }
    
    // We should have exactly one error message (from the third message)
    expect($errorCount)->toBe(1);
    
    // We should have more messages after sending the fourth message
    expect(count($connection->messages))->toBeGreaterThan($messageCountBefore);
}); 