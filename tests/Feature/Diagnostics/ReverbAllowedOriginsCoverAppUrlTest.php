<?php

use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbAllowedOriginsCoverAppUrl;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbAllowedOriginsCoverAppUrl::class);
});

it('skips when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('skips when no application matches the broadcast connection', function () {
    configureReverbOrigins(broadcasterKey: 'unknown-key');

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('No Reverb application matches the broadcast connection, so allowed origins were not checked.');
});

it('skips when the application url is not configured', function () {
    configureReverbOrigins(origins: ['example.com']);

    config(['app.url' => null]);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The application URL is not configured, so allowed origins were not checked.');
});

it('fails when no origins are allowed', function () {
    configureReverbOrigins(origins: []);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The Reverb application does not allow any origins.');
});

it('passes when every origin is allowed', function () {
    configureReverbOrigins(origins: ['*']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('The Reverb application allows connections from any origin.');
});

it('fails when the app host is not allowed', function () {
    configureReverbOrigins(origins: ['example.com']);

    config(['app.url' => 'https://myapp.test']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The Reverb application\'s allowed origins do not include the application\'s own host.');
    expect($result->details)->toBe('- myapp.test');
});

it('fails when the frontend host is not allowed', function () {
    configureReverbOrigins(origins: ['myapp.test']);

    config(['app.url' => 'https://myapp.test', 'app.frontend_url' => 'https://front.test']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->details)->toBe('- front.test');
});

it('ignores the app url when a frontend url is configured', function () {
    configureReverbOrigins(origins: ['front.test']);

    config(['app.url' => 'https://api.test', 'app.frontend_url' => 'https://front.test']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Pass);
});

it('passes when the app host is allowed', function () {
    configureReverbOrigins(origins: ['myapp.test']);

    config(['app.url' => 'https://myapp.test']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('The Reverb application allows connections from the application\'s host.');
});

it('passes when a wildcard origin matches', function () {
    configureReverbOrigins(origins: ['*.myapp.test']);

    config(['app.url' => 'https://ws.myapp.test']);

    $result = (new ReverbAllowedOriginsCoverAppUrl)->check();

    expect($result->status)->toBe(Status::Pass);
});

/**
 * Configure the Reverb broadcast connection and an application with the given origins.
 *
 * @param  list<string>  $origins
 */
function configureReverbOrigins(array $origins = ['*'], string $broadcasterKey = 'reverb-key'): void
{
    config([
        'app.frontend_url' => null,
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => $broadcasterKey,
        ],
        'reverb.apps.provider' => 'config',
        'reverb.apps.apps' => [[
            'key' => 'reverb-key',
            'secret' => 'reverb-secret',
            'app_id' => '123456',
            'allowed_origins' => $origins,
        ]],
    ]);
}
