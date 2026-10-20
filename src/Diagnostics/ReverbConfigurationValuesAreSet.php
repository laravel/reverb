<?php

namespace Laravel\Reverb\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use Laravel\Doctor\Support\Details;

class ReverbConfigurationValuesAreSet extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb config values are set';

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
            'set' => 'Every configuration value required by the active Reverb broadcast connection is set.',
            'missing' => Message::make(
                summary: 'Some configuration values required by the active Reverb broadcast connection are not set.',
                remediation: 'Set the missing values, typically by defining their environment variables in .env or the deployment environment.',
            )->link(Link::docs('reverb', 'configuration')),
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

        $prefix = "broadcasting.connections.{$connection}";

        $missing = Configured::missing([
            "{$prefix}.app_id",
            "{$prefix}.key",
            "{$prefix}.secret",
            "{$prefix}.options.host",
        ]);

        if ($missing === []) {
            return $this->pass('set');
        }

        return $this->fail('missing')
            ->withDetails(Details::bullets($missing));
    }
}
