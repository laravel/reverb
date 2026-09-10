<?php

use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbBroadcasterCredentialsMatchApp;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbBroadcasterCredentialsMatchApp::class);
});

it('skips when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('skips when apps are not managed by the config provider', function () {
    configureReverbCredentials();

    config(['reverb.apps.provider' => 'custom']);

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('Reverb applications are not managed by the config provider.');
});

it('skips when the broadcast connection is not configured', function () {
    configureReverbCredentials(broadcaster: ['key' => null]);

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The Reverb broadcast connection is missing required configuration.');
});

it('fails when no application matches the key', function () {
    configureReverbCredentials(broadcaster: ['key' => 'unknown-key']);

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('No Reverb application matches the broadcast connection\'s key.');
    expect($result->remediation)->toContain('REVERB_APP_KEY');
});

it('fails when the matched application has different credentials', function () {
    configureReverbCredentials(app: ['secret' => 'other-secret', 'app_id' => '999999']);

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The Reverb application matching the broadcast connection\'s key has different credentials.');
    expect($result->details)->toBe(
        "- The application's app_id does not match the broadcast connection.\n- The application's secret does not match the broadcast connection."
    );
});

it('passes when the credentials match', function () {
    configureReverbCredentials();

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('The broadcast connection credentials match a Reverb application.');
});

it('passes when a numeric app id matches', function () {
    configureReverbCredentials(app: ['app_id' => 123456]);

    $result = (new ReverbBroadcasterCredentialsMatchApp)->check();

    expect($result->status)->toBe(Status::Pass);
});

/**
 * Configure the Reverb broadcast connection and a matching application.
 *
 * @param  array<string, mixed>  $broadcaster
 * @param  array<string, mixed>  $app
 */
function configureReverbCredentials(array $broadcaster = [], array $app = []): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'reverb-key',
            'secret' => 'reverb-secret',
            'app_id' => '123456',
            ...$broadcaster,
        ],
        'reverb.apps.provider' => 'config',
        'reverb.apps.apps' => [[
            'key' => 'reverb-key',
            'secret' => 'reverb-secret',
            'app_id' => '123456',
            ...$app,
        ]],
    ]);
}
