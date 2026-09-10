<?php

namespace Laravel\Reverb\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use Laravel\Doctor\Support\Details;

class ReverbBroadcasterCredentialsMatchApp extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb broadcaster credentials match an app';

    public string $group = 'reverb';

    /**
     * Get the diagnostic's named message definitions.
     *
     * @return array<string, string|Message>
     */
    protected function messages(): array
    {
        return [
            'not-used' => 'The active broadcast connection does not use Reverb.',
            'not-config-provider' => 'Reverb applications are not managed by the config provider.',
            'not-configured' => 'The Reverb broadcast connection is missing required configuration.',
            'unknown-key' => Message::make(
                summary: 'No Reverb application matches the broadcast connection\'s key.',
                remediation: 'Set the broadcast connection and the `reverb.apps.apps` entry to the same `REVERB_APP_KEY`.',
            )->link(Link::docs('reverb', 'application-credentials')),
            'mismatched' => Message::make(
                summary: 'The Reverb application matching the broadcast connection\'s key has different credentials.',
                remediation: 'Align the app ID and secret between the broadcast connection and `reverb.apps.apps`.',
            )->link(Link::docs('reverb', 'application-credentials')),
            'matched' => 'The broadcast connection credentials match a Reverb application.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        $connection = $this->reverbConnection();

        if ($connection === null) {
            return $this->skip('not-used');
        }

        if (Configured::string('reverb.apps.provider') !== 'config') {
            return $this->skip('not-config-provider');
        }

        $prefix = "broadcasting.connections.{$connection}";

        $key = Configured::string("{$prefix}.key");
        $secret = Configured::string("{$prefix}.secret");
        $appId = Configured::string("{$prefix}.app_id");

        if ($key === null || $secret === null || $appId === null) {
            return $this->skip('not-configured');
        }

        $app = $this->applicationWithKey($key);

        if ($app === null) {
            return $this->fail('unknown-key');
        }

        $mismatched = array_values(array_filter([
            (string) ($app['app_id'] ?? '') === $appId ? null : 'app_id',
            (string) ($app['secret'] ?? '') === $secret ? null : 'secret',
        ]));

        if ($mismatched !== []) {
            return $this->fail('mismatched')
                ->withDetails(Details::bullets(array_map(
                    static fn (string $credential): string => "The application's {$credential} does not match the broadcast connection.",
                    $mismatched,
                )));
        }

        return $this->pass('matched');
    }
}
