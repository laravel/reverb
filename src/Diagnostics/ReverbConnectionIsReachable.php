<?php

namespace Laravel\Reverb\Diagnostics;

use Illuminate\Broadcasting\BroadcastManager;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use RuntimeException;
use Throwable;

class ReverbConnectionIsReachable extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb connects';

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
            'not-configured' => 'The Reverb broadcast connection is missing required configuration.',
            'unreachable' => Message::make(
                summary: 'The application cannot connect to Reverb using broadcast connection [{connection}].',
                remediation: 'Check the Reverb public host, port, scheme, path, app credentials, and server process.',
            )->link(Link::docs('reverb', 'running-server')),
            'reachable' => 'The application can connect to Reverb using broadcast connection [{connection}].',
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

        if ($missing !== []) {
            return $this->skip('not-configured');
        }

        /** @var array<string, mixed> $configuration */
        $configuration = config($prefix);
        $options = $configuration['options'] ?? [];
        $clientOptions = $configuration['client_options'] ?? [];

        $configuration['options'] = [
            ...(is_array($options) ? $options : []),
            'timeout' => 2,
        ];

        $configuration['client_options'] = [
            ...(is_array($clientOptions) ? $clientOptions : []),
            'connect_timeout' => 2,
            'timeout' => 2,
        ];

        try {
            $response = app(BroadcastManager::class)->pusher($configuration)->get('/channels');

            if (! is_object($response) || ! property_exists($response, 'channels')) {
                throw new RuntimeException('The Reverb channels endpoint returned an unexpected response.');
            }
        } catch (Throwable $e) {
            return $this->fail('unreachable', ['connection' => $connection])
                ->withDetails($e->getMessage());
        }

        return $this->pass('reachable', ['connection' => $connection]);
    }
}
