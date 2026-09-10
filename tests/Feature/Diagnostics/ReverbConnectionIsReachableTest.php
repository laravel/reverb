<?php

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbConnectionIsReachable;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbConnectionIsReachable::class);
});

it('skips without probing when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbConnectionIsReachable)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('skips without probing when required configuration is missing', function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'app_id' => null,
            'key' => 'key',
            'secret' => '',
            'options' => ['host' => null],
        ],
    ]);

    $result = (new ReverbConnectionIsReachable)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The Reverb broadcast connection is missing required configuration.');
});

it('uses a signed read-only request to probe reverb', function () {
    $history = [];
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], '{"channels":{}}'),
    ]));
    $stack->push(Middleware::history($history));

    configureReachableReverb($stack);

    $result = (new ReverbConnectionIsReachable)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('The application can connect to Reverb using broadcast connection [reverb].');
    expect($history)->toHaveCount(1);
    expect($history[0]['request']->getMethod())->toBe('GET');
    expect($history[0]['request']->getUri()->getPath())->toBe('/apps/123456/channels');
    expect($history[0]['request']->getUri()->getQuery())->toContain('auth_key=reverb-key');
    expect($history[0]['request']->getUri()->getQuery())->toContain('auth_signature=');
    expect($history[0]['options']['connect_timeout'])->toBe(2);
    expect($history[0]['options']['timeout'])->toBe(2);
});

it('fails when the endpoint does not return a reverb response', function () {
    $stack = HandlerStack::create(new MockHandler([
        new Response(200, [], '{"status":"ok"}'),
    ]));

    configureReachableReverb($stack);

    $result = (new ReverbConnectionIsReachable)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->details)->toBe('The Reverb channels endpoint returned an unexpected response.');
});

it('fails when reverb rejects the connection', function () {
    $stack = HandlerStack::create(new MockHandler([
        new Response(401, [], 'Invalid credentials.'),
    ]));

    configureReachableReverb($stack);

    $result = (new ReverbConnectionIsReachable)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The application cannot connect to Reverb using broadcast connection [reverb].');
    expect($result->details)->toContain('Invalid credentials.');
});

/**
 * Configure a Reverb broadcast connection backed by the given Guzzle handler.
 */
function configureReachableReverb(callable $handler): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'app_id' => '123456',
            'key' => 'reverb-key',
            'secret' => 'reverb-secret',
            'options' => [
                'host' => 'reverb.test',
                'port' => 443,
                'scheme' => 'https',
                'useTLS' => true,
                'path' => '/reverb',
            ],
            'client_options' => ['handler' => $handler],
        ],
    ]);
}
