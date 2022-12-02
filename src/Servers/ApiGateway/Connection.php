<?php

namespace Laravel\Reverb\Servers\ApiGateway;

use Aws\ApiGatewayManagementApi\ApiGatewayManagementApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Concerns\GeneratesPusherIdentifiers;
use Laravel\Reverb\Concerns\SerializesConnections;
use Laravel\Reverb\Connection as BaseConnection;
use Laravel\Reverb\Contracts\SerializableConnection;
use Laravel\Reverb\Output;
use Throwable;

class Connection extends BaseConnection implements SerializableConnection
{
    use GeneratesPusherIdentifiers, SerializesConnections, EnsuresIntegrity;

    /**
     * The normalized socket ID.
     *
     * @var string
     */
    protected string $id;

    /**
     * The cache configuration array.
     */
    protected array $config;

    public function __construct(
        protected string $identifier,
        protected Application $application
    ) {
        parent::__construct($application);
        $this->config = Config::get('reverb.connections.api_gateway');
        $this->repository = Cache::store($this->config['connection_cache']['store']);
    }

    /**
     * Make a new connection and connect to the application.
     *
     * @param  string  $identifier
     * @param  \Laravel\Reverb\Application  $application
     * @return \Laravel\Reverb\Servers\ApiGateway\Connection
     */
    public static function make(string $identifier, Application $application): Connection
    {
        $connection = new static($identifier, $application);

        return $connection->connect();
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
     * Terminate a connection.
     *
     * @return void
     */
    public function disconnect(): void
    {
        //
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

    /**
     * Get the cache key for the connections.
     *
     * @return string
     */
    protected function key(): string
    {
        return "{$this->config['connection_cache']['prefix']}:{$this->application->id()}:connections";
    }
}
