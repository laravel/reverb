<?php

namespace Laravel\Reverb\Diagnostics;

use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;

class ReverbDefaultServerIsDefined extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb server is defined';

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
            'not-defined' => Message::make(
                summary: 'The default Reverb server [{server}] is not defined in `reverb.servers`.',
                remediation: 'Define the [{server}] server in `reverb.servers` so the Reverb server has host, port, and scaling configuration.',
            )->link(Link::docs('reverb', 'configuration')),
            'defined' => 'The default Reverb server [{server}] is defined.',
        ];
    }

    /**
     * Run the diagnostic.
     *
     * A default naming an unsupported driver dies during service provider
     * registration, before Doctor can run, so only a missing or empty server
     * entry for the resolvable default is observable here.
     */
    public function check(): DiagnosticResult
    {
        if ($this->reverbConnection() === null) {
            return $this->skip('not-used');
        }

        $server = $this->defaultServer();

        if ($this->defaultServerConfiguration() === null) {
            return $this->fail('not-defined', ['server' => $server]);
        }

        return $this->pass('defined', ['server' => $server]);
    }
}
