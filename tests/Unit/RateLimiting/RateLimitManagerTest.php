<?php

use Illuminate\Cache\RateLimiter;
use Laravel\Reverb\RateLimiting\RateLimitManager;
use Laravel\Reverb\Protocols\Pusher\Exceptions\RateLimitExceededException;
use Laravel\Reverb\Tests\FakeConnection;

beforeEach(function () {
    $this->rateLimiter = Mockery::mock(RateLimiter::class);
    $this->connection = new FakeConnection();
    
    $this->rateLimitManager = new RateLimitManager(
        $this->rateLimiter,
        10,
        10
    );
});

afterEach(function () {
    Mockery::close();
});

it('allows messages when under the rate limit', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(false);
    
    $this->rateLimiter->shouldReceive('hit')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(1);
    
    $this->rateLimitManager->handle($this->connection);
    
    expect(true)->toBeTrue();
});

it('throws an exception when over the rate limit', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(true);
    
    $this->expectException(RateLimitExceededException::class);
    
    $this->rateLimitManager->handle($this->connection);
});

it('uses the correct request signature', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(false);
    
    $this->rateLimiter->shouldReceive('hit')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(1);
    
    $this->rateLimitManager->handle($this->connection);
    
    expect(true)->toBeTrue();
});

it('respects custom max attempts', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));
    $customMaxAttempts = 5;

    $rateLimitManager = new RateLimitManager(
        $this->rateLimiter,
        $customMaxAttempts,
        10
    );

    $this->rateLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($expectedKey, $customMaxAttempts)
        ->andReturn(false);
    
    $this->rateLimiter->shouldReceive('hit')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(1);
    
    $rateLimitManager->handle($this->connection);
    
    expect(true)->toBeTrue();
});

it('respects custom decay seconds', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));
    $customDecaySeconds = 30;

    $rateLimitManager = new RateLimitManager(
        $this->rateLimiter,
        10,
        $customDecaySeconds
    );

    $this->rateLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(false);
    
    $this->rateLimiter->shouldReceive('hit')
        ->once()
        ->with($expectedKey, $customDecaySeconds)
        ->andReturn(1);
    
    $rateLimitManager->handle($this->connection);
    
    expect(true)->toBeTrue();
});

it('can check if a connection would exceed rate limit', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('tooManyAttempts')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(true);
    
    expect($this->rateLimitManager->wouldExceedRateLimit($this->connection))->toBeTrue();
});

it('can get remaining attempts for a connection', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('remaining')
        ->once()
        ->with($expectedKey, 10)
        ->andReturn(5);
    
    expect($this->rateLimitManager->remainingAttempts($this->connection))->toBe(5);
});

it('can get time until rate limit is reset', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('availableIn')
        ->once()
        ->with($expectedKey)
        ->andReturn(60);
    
    expect($this->rateLimitManager->availableIn($this->connection))->toBe(60);
});

it('can clear rate limits for a connection', function () {
    $expectedKey = sha1(implode('|', [
        $this->connection->id(),
        $this->connection->app()->id(),
    ]));

    $this->rateLimiter->shouldReceive('clear')
        ->once()
        ->with($expectedKey)
        ->andReturn(null);
    
    $this->rateLimitManager->clear($this->connection);
    
    expect(true)->toBeTrue();
}); 