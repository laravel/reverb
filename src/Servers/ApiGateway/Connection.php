<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Aws\ApiGatewayManagementApi\ApiGatewayManagementApiClient;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Concerns\SerializesConnections;
use Laravel\Reverb\Contracts\Connection as ConnectionInterface;
use Laravel\Reverb\Contracts\SerializableConnection;
use Throwable;

class Connection implements ConnectionInterface, SerializableConnection
{
    use GeneratesPusherIdentifiers, SerializesConnections;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected $id;

    public function __construct(protected string $identifier)
    {
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
                echo 'Unable to send message to connection: '.$e->getMessage();
            }
        });
    }
}
