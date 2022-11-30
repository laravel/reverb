<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Aws\ApiGatewayManagementApi\ApiGatewayManagementApiClient;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Concerns\SerializesConnections;
use Laravel\Reverb\Connection as BaseConnection;
use Laravel\Reverb\Contracts\SerializableConnection;
use Laravel\Reverb\Output;
use Throwable;

class Connection extends BaseConnection implements SerializableConnection
{
    use GeneratesPusherIdentifiers, SerializesConnections;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    public function __construct(
        protected string $identifier,
        protected Application $application
    ) {
        parent::__construct($application);
    }

    /**
     * Get the raw socket connection identifier.
     *
     * @return string
     */
    public function identifier(): string
    {
        return (string) $this->identifier;
    }

    /**
     * Get the normalized socket ID.
     *
     * @return string
     */
    public function id(): string
    {
        if (! $this->id) {
            $this->id = $this->generateId();
        }

        return $this->id;
    }

    /**
     * Send a message to the connection.
     *
     * @param  string  $message
     * @return void
     */
    public function send(string $message): void
    {
        dispatch(function () use ($message) {
            try {
                $client = new ApiGatewayManagementApiClient([
                    'region' => Config::get('reverb.servers.api_gateway.region'),
                    'endpoint' => Config::get('reverb.servers.api_gateway.endpoint'),
                    'version' => 'latest',
                ]);

                $client->postToConnection([
                    'ConnectionId' => $this->identifier,
                    'Data' => $message,
                ]);
            } catch (Throwable $e) {
                Output::error('Unable to send message.');
                Output::info($e->getMessage());
            }
        });
    }

    /**
     * Get the application the connection belongs to.
     *
     * @return \Laravel\Reverb\Application
     */
    public function app(): Application
    {
        return $this->application;
    }
}
