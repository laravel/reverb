<?php

namespace Laravel\Reverb\Diagnostics;

use Illuminate\Support\Str;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use Laravel\Doctor\Support\Details;

class ReverbAllowedOriginsCoverAppUrl extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb allows the app origin';

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
            'no-app' => 'No Reverb application matches the broadcast connection, so allowed origins were not checked.',
            'no-app-url' => 'The application URL is not configured, so allowed origins were not checked.',
            'no-origins' => Message::make(
                summary: 'The Reverb application does not allow any origins.',
                remediation: 'Add the application\'s host to `allowed_origins` in `reverb.apps.apps` so browser connections are not rejected.',
            )->link(Link::docs('reverb', 'allowed-origins')),
            'all-origins' => 'The Reverb application allows connections from any origin.',
            'not-covered' => Message::make(
                summary: 'The Reverb application\'s allowed origins do not include the application\'s own host.',
                remediation: 'Add the host to `allowed_origins` in `reverb.apps.apps` so browser connections are not rejected.',
            )->link(Link::docs('reverb', 'allowed-origins')),
            'covered' => 'The Reverb application allows connections from the application\'s host.',
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

        $app = $this->applicationWithKey(
            Configured::string("broadcasting.connections.{$connection}.key"),
        );

        if ($app === null) {
            return $this->skip('no-app');
        }

        $origins = $app['allowed_origins'] ?? null;

        if (! is_array($origins) || $origins === []) {
            return $this->fail('no-origins');
        }

        if (in_array('*', $origins, true)) {
            return $this->pass('all-origins');
        }

        $host = $this->applicationHost();

        if ($host === null) {
            return $this->skip('no-app-url');
        }

        $covered = collect($origins)->contains(
            static fn ($origin): bool => is_string($origin) && Str::is($origin, $host),
        );

        if (! $covered) {
            return $this->fail('not-covered')
                ->withDetails(Details::bullets([$host]));
        }

        return $this->pass('covered');
    }

    /**
     * Get the host browsers will send as the connection origin.
     *
     * Browsers originate connections from the frontend, so a configured
     * frontend URL supersedes the application URL, which may be an API
     * host that never opens browser connections.
     */
    private function applicationHost(): ?string
    {
        foreach (['app.frontend_url', 'app.url'] as $key) {
            $url = Configured::string($key);
            $host = $url === null ? null : parse_url($url, PHP_URL_HOST);

            if (is_string($host) && $host !== '') {
                return $host;
            }
        }

        return null;
    }
}
