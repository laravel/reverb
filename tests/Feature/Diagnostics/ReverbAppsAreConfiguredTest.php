<?php

use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbAppsAreConfigured;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbAppsAreConfigured::class);
});

it('skips when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('skips when apps are not managed by the config provider', function () {
    activateReverbApps();

    config(['reverb.apps.provider' => 'custom']);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('Reverb applications are not managed by the config provider.');
});

it('fails when no apps are defined', function () {
    activateReverbApps();

    config(['reverb.apps.apps' => []]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('No Reverb applications are defined.');
});

it('fails with the missing credential paths', function () {
    activateReverbApps();

    config(['reverb.apps.apps' => [
        reverbApp(['secret' => null, 'app_id' => '']),
    ]]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('Some Reverb applications are missing credentials.');
    expect($result->details)->toBe(
        "- reverb.apps.apps.0.app_id\n- reverb.apps.apps.0.secret"
    );
});

it('skips when the broadcast connection is missing the same credentials', function () {
    activateReverbApps();

    config([
        'broadcasting.connections.reverb' => ['driver' => 'reverb'],
        'reverb.apps.apps' => [
            reverbApp(['key' => null, 'secret' => null, 'app_id' => null]),
        ],
    ]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The Reverb broadcast connection is missing required configuration.');
});

it('still reports credentials the broadcast connection has', function () {
    activateReverbApps();

    config([
        'broadcasting.connections.reverb.app_id' => null,
        'reverb.apps.apps' => [
            reverbApp(['app_id' => null]),
            reverbApp(['key' => 'second-key', 'app_id' => '222222', 'secret' => null]),
        ],
    ]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->details)->toBe('- reverb.apps.apps.1.secret');
});

it('fails when an app omits options the server requires', function () {
    activateReverbApps();

    $app = reverbApp();
    unset($app['ping_interval'], $app['max_message_size']);

    config(['reverb.apps.apps' => [$app]]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('Some Reverb applications are missing options the server requires.');
    expect($result->details)->toBe(
        "- reverb.apps.apps.0.ping_interval\n- reverb.apps.apps.0.max_message_size"
    );
});

it('fails when applications share credentials', function () {
    activateReverbApps();

    config(['reverb.apps.apps' => [
        reverbApp(['secret' => 'first-secret', 'app_id' => '111111']),
        reverbApp(['secret' => 'second-secret', 'app_id' => '222222']),
    ]]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('Multiple Reverb applications share the same key or app ID.');
    expect($result->details)->toBe('- key [reverb-key] is used by 2 applications');
});

it('passes when every app is configured', function () {
    activateReverbApps();

    config(['reverb.apps.apps' => [
        reverbApp(['key' => 'first-key', 'app_id' => '111111']),
        reverbApp(['key' => 'second-key', 'app_id' => 222222]),
    ]]);

    $result = (new ReverbAppsAreConfigured)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('Every Reverb application has the credentials and options the server requires.');
});

/**
 * Activate Reverb with config-provided applications.
 */
function activateReverbApps(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            'key' => 'reverb-key',
            'secret' => 'reverb-secret',
            'app_id' => '123456',
        ],
        'reverb.apps.provider' => 'config',
    ]);
}

/**
 * Build a fully configured application.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function reverbApp(array $overrides = []): array
{
    return [
        'key' => 'reverb-key',
        'secret' => 'reverb-secret',
        'app_id' => '123456',
        'ping_interval' => 60,
        'allowed_origins' => ['*'],
        'max_message_size' => 10_000,
        ...$overrides,
    ];
}
