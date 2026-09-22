<?php

namespace Laravel\Reverb\Diagnostics;

use Illuminate\Redis\RedisManager;
use Laravel\Doctor\Diagnostic;
use Laravel\Doctor\Results\DiagnosticResult;
use Laravel\Doctor\Results\Link;
use Laravel\Doctor\Results\Message;
use Laravel\Doctor\Support\Configured;
use Throwable;

class ReverbScalingRedisIsReachable extends Diagnostic
{
    use ResolvesReverbConfiguration;

    public string $name = 'Reverb scaling Redis connects';

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
            'no-server' => 'The default Reverb server is not defined.',
            'disabled' => 'Reverb scaling is disabled.',
            'unreachable' => Message::make(
                summary: 'The Redis server used for Reverb scaling cannot be reached.',
                remediation: 'Check the `REDIS_*` values used by Reverb\'s `scaling.server` configuration.',
            )->link(Link::docs('reverb', 'scaling')),
            'reachable' => 'The Redis server used for Reverb scaling is reachable.',
        ];
    }

    /**
     * Run the diagnostic.
     */
    public function check(): DiagnosticResult
    {
        if ($this->reverbConnection() === null) {
            return $this->skip('not-used');
        }

        $configuration = $this->defaultServerConfiguration();

        if ($configuration === null) {
            return $this->skip('no-server');
        }

        if (! ($configuration['scaling']['enabled'] ?? false)) {
            return $this->skip('disabled');
        }

        try {
            $this->probe((array) ($configuration['scaling']['server'] ?? []));
        } catch (Throwable $e) {
            return $this->fail('unreachable')->withDetails($e->getMessage());
        }

        return $this->pass('reachable');
    }

    /**
     * Probe the Redis server Reverb publishes scaling events through.
     *
     * Reverb falls back to the default framework Redis connection when the
     * scaling server configuration is empty, so the probe does the same.
     *
     * @param  array<string, mixed>  $configuration
     */
    private function probe(array $configuration): void
    {
        if ($configuration === []) {
            $configuration = (array) config('database.redis.default');
        }

        $manager = new RedisManager(
            app(),
            Configured::string('database.redis.client', 'phpredis'),
            ['default' => [...$configuration, 'timeout' => 2.0]],
        );

        $manager->connection()->ping();
    }
}
