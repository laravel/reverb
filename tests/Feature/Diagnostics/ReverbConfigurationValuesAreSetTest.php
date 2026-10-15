<?php

use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbConfigurationValuesAreSet;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbConfigurationValuesAreSet::class);
});

it('skips when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbConfigurationValuesAreSet)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('passes when required values are set', function () {
    configureReverbConnectionValues([
        'app_id' => '123456',
        'key' => 'reverb-key',
        'secret' => 'reverb-secret',
        'options' => ['host' => 'reverb.test'],
    ]);

    $result = (new ReverbConfigurationValuesAreSet)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('Every configuration value required by the active Reverb broadcast connection is set.');
});

it('fails with the missing configuration keys', function () {
    configureReverbConnectionValues([
        'app_id' => null,
        'key' => 'reverb-key',
        'secret' => '',
        'options' => ['host' => null],
    ]);

    $result = (new ReverbConfigurationValuesAreSet)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('Some configuration values required by the active Reverb broadcast connection are not set.');
    expect($result->details)->toBe(
        "- broadcasting.connections.reverb.app_id\n- broadcasting.connections.reverb.secret\n- broadcasting.connections.reverb.options.host"
    );
});

/**
 * Configure the Reverb broadcast connection.
 *
 * @param  array<string, mixed>  $configuration
 */
function configureReverbConnectionValues(array $configuration): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => [
            'driver' => 'reverb',
            ...$configuration,
        ],
    ]);
}
