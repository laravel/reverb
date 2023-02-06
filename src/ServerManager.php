<?php

namespace Laravel\Reverb;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Manager;
use Laravel\Reverb\Servers\ApiGateway\ApiGatewayProvider;
use Laravel\Reverb\Servers\Ratchet\RatchetProvider;

class ServerManager extends Manager
{
    public function __construct(protected Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('reverb.default', 'ratchet');
    }

    /**
     * Creates the Ratchet driver.
     */
    public function createRatchetDriver(): RatchetProvider
    {
        return new RatchetProvider(
            $this->app,
            $this->config->get('reverb.servers.ratchet', [])
        );
    }

    /**
     * Creates the API Gateway driver.
     */
    public function createApiGatewayDriver(): ApiGatewayProvider
    {
        return new ApiGatewayProvider(
            $this->app,
            $this->config->get('reverb.servers.api_gateway', [])
        );
    }
}
