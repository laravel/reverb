<?php

namespace Reverb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Reverb\Contracts\ChannelManager;

class RedisSubscribe extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:subscribe';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Reverb Redis subscriber';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Redis::subscribe(['websockets'], function ($message) {
            $event = json_decode($message, true);

            foreach (app(ChannelManager::class)->all() as $connection) {
                $connection->send(json_encode([
                    'event' => $event['name'],
                    'channel' => $event['channel'],
                    'data' => $event['data'],
                ]));
            }
        });
    }
}
