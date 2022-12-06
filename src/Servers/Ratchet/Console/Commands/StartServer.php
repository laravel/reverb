<?php

namespace Laravel\Reverb\Servers\Ratchet\Console\Commands;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Exception;
use Illuminate\Console\Command;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Event;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Output;
use Laravel\Reverb\Servers\Ratchet\Factory as ServerFactory;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class StartServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ratchet:start
                {--host=}
                {--port=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Ratchet Reverb server';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->laravel->instance(Logger::class, new CliLogger($this->output));

        $config = $this->laravel['config']['reverb.servers.ratchet'];
        $host = $this->option('host') ?: $config['host'];
        $port = $this->option('port') ?: $config['port'];

        $loop = Loop::get();

        $this->bindRedis($loop);
        $this->subscribe($loop);
        $this->scheduleCleanup($loop);

        $server = ServerFactory::make($host, $port, $loop);

        $this->components->info("Starting server on {$host}:{$port}");

        $server->run();
    }

    /**
     * Get the connection URL for Redis.
     *
     * @return string
     */
    protected function redisUrl(): string
    {
        $config = $this->laravel->config['database.redis.default'];

        $host = $config['host'];
        $port = $config['port'] ?: 6379;

        $query = [];

        if ($config['password']) {
            $query['password'] = $config['password'];
        }

        if ($config['database']) {
            $query['db'] = $config['database'];
        }

        $query = http_build_query($query);

        return "redis://{$host}:{$port}".($query ? "?{$query}" : '');
    }

    /**
     * Bind the Redis client to the container.
     *
     * @param  \React\EventLoop\LoopInterface  $loop
     * @return void
     */
    protected function bindRedis(LoopInterface $loop): void
    {
        $this->laravel->singleton(Client::class, function () use ($loop) {
            return (new Factory($loop))->createLazyClient(
                $this->redisUrl()
            );
        });
    }

    protected function scheduleCleanup(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(60, function () {
            Output::info('Pruning Stale Connections');
            PruneStaleConnections::dispatch();

            Output::info('Pinging Inactive Connections');
            PingInactiveConnections::dispatch();
        });
    }

    /**
     * Subscribe to the Redis pub / sub channel.
     *
     * @param  \React\EventLoop\LoopInterface  $loop
     * @return void
     */
    protected function subscribe(LoopInterface $loop): void
    {
        $config = $this->laravel['config']['reverb']['pubsub'];

        if (! $config['enabled']) {
            return;
        }

        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe($config['channel']);

        $redis->on('error', function (Exception $e) {
            $this->components->error($e->getMessage());
        });

        $redis->on('message', function (string $channel, string $payload) {
            $event = json_decode($payload, true);
            Event::dispatchSynchronously(
                unserialize($event['application']),
                $event['payload']
            );
        });
    }
}
