<?php

use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbScalingRedisIsReachable;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbScalingRedisIsReachable::class);
});

it('skips when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbScalingRedisIsReachable)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('skips when the default server is not defined', function () {
    activateReverbForScaling();

    config(['reverb.servers' => []]);

    $result = (new ReverbScalingRedisIsReachable)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The default Reverb server is not defined.');
});

it('skips when scaling is disabled', function () {
    activateReverbForScaling();

    config(['reverb.servers.reverb.scaling.enabled' => false]);

    $result = (new ReverbScalingRedisIsReachable)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('Reverb scaling is disabled.');
});

it('fails when the scaling redis server is unreachable', function () {
    activateReverbForScaling();

    config([
        'reverb.servers.reverb.scaling.enabled' => true,
        'reverb.servers.reverb.scaling.server' => ['host' => '127.0.0.1', 'port' => 1],
    ]);

    $result = (new ReverbScalingRedisIsReachable)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The Redis server used for Reverb scaling cannot be reached.');
    expect($result->details)->not->toBe('');
});

it('falls back to the default redis connection', function () {
    activateReverbForScaling();

    config([
        'reverb.servers.reverb.scaling.enabled' => true,
        'reverb.servers.reverb.scaling.server' => [],
        'database.redis.default' => ['host' => '127.0.0.1', 'port' => 1],
    ]);

    $result = (new ReverbScalingRedisIsReachable)->check();

    expect($result->status)->toBe(Status::Fail);
});

/**
 * Activate Reverb as the default broadcast connection.
 */
function activateReverbForScaling(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => ['driver' => 'reverb'],
    ]);
}
