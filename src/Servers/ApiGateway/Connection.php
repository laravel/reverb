<?php

namespace Reverb\Servers\ApiGateway;

use Aws\ApiGatewayManagementApi\ApiGatewayManagementApiClient;
use Illuminate\Support\Facades\Config;
use Reverb\Concerns\GeneratesPusherIdentifiers;
use Reverb\Concerns\SerializesConnections;
use Reverb\Contracts\Connection as ConnectionInterface;
use Throwable;

class Connection implements ConnectionInterface
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
        return $this->identifier;
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
