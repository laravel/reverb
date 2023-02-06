<?php

namespace Laravel\Reverb;

use Illuminate\Support\Manager;

class ApplicationManager extends Manager
{
    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('reverb.apps.provider', 'config');
    }

    /**
     * Creates the configuration driver.
     */
    public function createConfigDriver(): ConfigProvider
    {
        return new ConfigProvider(
            collect($this->config->get('reverb.apps.apps', []))
        );
    }
}
