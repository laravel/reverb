<?php

namespace Laravel\Reverb;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Manager;
use Laravel\Reverb\Servers\ApiGateway\ApiGatewayProvider;
use Laravel\Reverb\Servers\Reverb\ReverbProvider;

class ServerManager extends Manager
{
    /**
     * Create a new server manager instance.
     */
    public function __construct(protected Application $app)
    {
        parent::__construct($app);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->config->get('reverb.default', 'reverb');
    }

    /**
     * Creates the Reverb driver.
     */
    public function createReverbDriver(): ReverbProvider
    {
        return new ReverbProvider(
            $this->app,
            $this->config->get('reverb.servers.reverb', [])
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
