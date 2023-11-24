<?php

namespace Laravel\Reverb\Servers\ApiGateway\Jobs;

use Aws\ApiGatewayManagementApi\ApiGatewayManagementApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Config;
use Throwable;

class SendToConnection implements ShouldQueue
{
    use Dispatchable;

    /**
     * Create a new job instance.
     */
    public function __construct(public string $connectionId, public string $message)
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $client = new ApiGatewayManagementApiClient([
                'region' => Config::get('reverb.servers.api_gateway.region'),
                'endpoint' => Config::get('reverb.servers.api_gateway.endpoint'),
                'version' => 'latest',
            ]);

            $client->postToConnection([
                'ConnectionId' => $this->connectionId,
                'Data' => $this->message,
            ]);
        } catch (Throwable $e) {
            //
        }
    }
}
