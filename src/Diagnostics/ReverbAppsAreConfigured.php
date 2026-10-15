<?php

namespace Laravel\Reverb\Diagnostics;

use Illuminate\Support\Str;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use Laravel\Doctor\Support\Details;

class ReverbAppsAreConfigured extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb apps are configured';

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
            'no-apps' => Message::make(
                summary: 'No Reverb applications are defined.',
                remediation: 'Add an application with a key, secret, and app ID to `reverb.apps.apps`.',
            )->link(Link::docs('reverb', 'application-credentials')),
            'missing-credentials' => Message::make(
                summary: 'Some Reverb applications are missing credentials.',
                remediation: 'Set `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET` in the deployment environment.',
            )->link(Link::docs('reverb', 'application-credentials')),
            'missing-options' => Message::make(
                summary: 'Some Reverb applications are missing options the server requires.',
                remediation: 'Add `ping_interval`, `allowed_origins`, and `max_message_size` to every application in `reverb.apps.apps`.',
            )->link(Link::docs('reverb', 'configuration')),
            'duplicate-credentials' => Message::make(
                summary: 'Multiple Reverb applications share the same key or app ID.',
                remediation: 'Give every application in `reverb.apps.apps` a unique key and app ID so lookups resolve the intended application.',
            )->link(Link::docs('reverb', 'additional-applications')),
            'configured' => 'Every Reverb application has the credentials and options the server requires.',
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

        $apps = config('reverb.apps.apps');

        if (! is_array($apps) || $apps === []) {
            return $this->fail('no-apps');
        }

        $missing = $this->missingCredentials($apps);

        if ($missing !== []) {
            $unexplained = $this->withoutSharedEnvironmentCredentials($missing, $connection);

            if ($unexplained === []) {
                return $this->skip('not-configured');
            }

            return $this->fail('missing-credentials')
                ->withDetails(Details::bullets($unexplained));
        }

        $missingOptions = $this->missingOptions($apps);

        if ($missingOptions !== []) {
            return $this->fail('missing-options')
                ->withDetails(Details::bullets($missingOptions));
        }

        $duplicated = $this->duplicatedCredentials($apps);

        if ($duplicated !== []) {
            return $this->fail('duplicate-credentials')
                ->withDetails(Details::bullets($duplicated));
        }

        return $this->pass('configured');
    }

    /**
     * Get the configuration paths of credentials missing from any application.
     *
     * @param  array<int|string, mixed>  $apps
     * @return list<string>
     */
    private function missingCredentials(array $apps): array
    {
        $missing = [];

        foreach (array_keys($apps) as $index) {
            $missing = [...$missing, ...Configured::missing([
                "reverb.apps.apps.{$index}.app_id",
                "reverb.apps.apps.{$index}.key",
                "reverb.apps.apps.{$index}.secret",
            ])];
        }

        return $missing;
    }

    /**
     * Discard missing paths whose credential the broadcast connection also lacks.
     *
     * When both sides lack the same credential the root cause is the shared
     * REVERB_APP_* environment variables, which ReverbConfigurationValuesAreSet
     * reports.
     *
     * @param  list<string>  $missing
     * @return list<string>
     */
    private function withoutSharedEnvironmentCredentials(array $missing, string $connection): array
    {
        $shared = array_map(
            static fn (string $path): string => Str::afterLast($path, '.'),
            Configured::missing([
                "broadcasting.connections.{$connection}.app_id",
                "broadcasting.connections.{$connection}.key",
                "broadcasting.connections.{$connection}.secret",
            ]),
        );

        return array_values(array_filter(
            $missing,
            static fn (string $path): bool => ! in_array(Str::afterLast($path, '.'), $shared, true),
        ));
    }

    /**
     * Get the paths of options the server reads without fallbacks but applications omit.
     *
     * ConfigApplicationProvider passes these to the Application constructor
     * directly, so a missing key crashes application lookups at runtime.
     *
     * @param  array<int|string, mixed>  $apps
     * @return list<string>
     */
    private function missingOptions(array $apps): array
    {
        $missing = [];

        foreach ($apps as $index => $app) {
            foreach (['ping_interval', 'allowed_origins', 'max_message_size'] as $option) {
                if (! is_array($app) || ! array_key_exists($option, $app)) {
                    $missing[] = "reverb.apps.apps.{$index}.{$option}";
                }
            }
        }

        return $missing;
    }

    /**
     * Describe keys and app IDs shared by more than one application.
     *
     * @param  array<int|string, mixed>  $apps
     * @return list<string>
     */
    private function duplicatedCredentials(array $apps): array
    {
        $duplicated = [];

        foreach (['key', 'app_id'] as $credential) {
            $counts = collect($apps)
                ->map(fn ($app) => is_array($app) ? ($app[$credential] ?? null) : null)
                ->filter(fn ($value): bool => is_scalar($value) && (string) $value !== '')
                ->countBy(fn ($value): string => (string) $value)
                ->filter(fn (int $count): bool => $count > 1);

            foreach ($counts as $value => $count) {
                $duplicated[] = sprintf('%s [%s] is used by %d applications', $credential, $value, $count);
            }
        }

        return $duplicated;
    }
}
