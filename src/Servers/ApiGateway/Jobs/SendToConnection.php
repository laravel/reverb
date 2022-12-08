<?php

namespace Laravel\Reverb\Servers\ApiGateway\Jobs;

use Aws\ApiGatewayManagementApi\ApiGatewayManagementApiClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Config;
use Laravel\Reverb\Output;
use Throwable;

class SendToConnection implements ShouldQueue
{
    use Dispatchable;

    /**
     * Create a new job instance.
     *
     * @param  string  $connectionId
     * @param  string  $message
     * @return void
     */
    public function __construct(public string $connectionId, public string $message)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
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
            Output::error('Unable to send message.');
            Output::info($e->getMessage());
        }
    }
}
