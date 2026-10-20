<?php

use Laravel\Doctor\Facades\Doctor;
use Laravel\Doctor\Results\Status;
use Laravel\Reverb\Diagnostics\ReverbDefaultServerIsDefined;
use Laravel\Reverb\Tests\Feature\Diagnostics\DiagnosticsTestCase;

uses(DiagnosticsTestCase::class);

it('is registered with doctor', function () {
    expect(Doctor::registered())->toContain(ReverbDefaultServerIsDefined::class);
});

it('skips when reverb is not active', function () {
    config(['broadcasting.default' => 'null']);

    $result = (new ReverbDefaultServerIsDefined)->check();

    expect($result->status)->toBe(Status::Skip);
    expect($result->summary)->toBe('The active broadcast connection does not use Reverb.');
});

it('fails when the default server entry is missing', function () {
    activateReverbForServer();

    config(['reverb.servers' => []]);

    $result = (new ReverbDefaultServerIsDefined)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The default Reverb server [reverb] is not defined in `reverb.servers`.');
    expect($result->remediation)->toContain('reverb.servers');
});

it('fails when the default server entry is empty', function () {
    activateReverbForServer();

    config(['reverb.servers.reverb' => []]);

    $result = (new ReverbDefaultServerIsDefined)->check();

    expect($result->status)->toBe(Status::Fail);
    expect($result->summary)->toBe('The default Reverb server [reverb] is not defined in `reverb.servers`.');
});

it('passes when the default server is defined', function () {
    activateReverbForServer();

    $result = (new ReverbDefaultServerIsDefined)->check();

    expect($result->status)->toBe(Status::Pass);
    expect($result->summary)->toBe('The default Reverb server [reverb] is defined.');
});

/**
 * Activate Reverb as the default broadcast connection.
 */
function activateReverbForServer(): void
{
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb' => ['driver' => 'reverb'],
    ]);
}
