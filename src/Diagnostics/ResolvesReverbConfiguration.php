<?php

namespace Laravel\Reverb\Diagnostics;

use Laravel\Doctor\Support\Configured;

trait ResolvesReverbConfiguration
{
    /**
     * Get the active broadcast connection name when it uses Reverb.
     */
    private function reverbConnection(): ?string
    {
        $connection = Configured::string('broadcasting.default', 'null');
        $driver = Configured::string("broadcasting.connections.{$connection}.driver", $connection);

        return $driver === 'reverb' ? $connection : null;
    }

    /**
     * Get the default Reverb server name.
     */
    private function defaultServer(): string
    {
        return Configured::string('reverb.default', 'reverb');
    }

    /**
     * Get the default Reverb server's configuration.
     *
     * @return array<string, mixed>|null
     */
    private function defaultServerConfiguration(): ?array
    {
        $configuration = config("reverb.servers.{$this->defaultServer()}");

        return is_array($configuration) && $configuration !== [] ? $configuration : null;
    }

    /**
     * Find the first configured application with the given key.
     *
     * @return array<string, mixed>|null
     */
    private function applicationWithKey(?string $key): ?array
    {
        if ($key === null) {
            return null;
        }

        foreach ((array) config('reverb.apps.apps') as $application) {
            if (is_array($application) && (string) ($application['key'] ?? '') === $key) {
                return $application;
            }
        }

        return null;
    }
}
