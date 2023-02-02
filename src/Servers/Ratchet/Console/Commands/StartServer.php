<?php

namespace Laravel\Reverb\Servers\Ratchet\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Output;
use Laravel\Reverb\Servers\Ratchet\Factory as ServerFactory;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;

class StartServer extends Command
{
    use InteractsWithAsyncRedis;

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
     */
    public function handle(): mixed
    {
        $this->laravel->instance(Logger::class, new CliLogger($this->output));

        $config = $this->laravel['config']['reverb.servers.ratchet'];
        $host = $this->option('host') ?: $config['host'];
        $port = $this->option('port') ?: $config['port'];

        $loop = Loop::get();

        $this->bindRedis($loop);
        $this->subscribeToRedis($loop);
        $this->scheduleCleanup($loop);

        $server = ServerFactory::make($host, $port, $loop);

        $this->components->info("Starting server on {$host}:{$port}");

        $server->run();
    }

    /**
     * Use the event loop to schedule periodic cleanup of connections.
     */
    protected function scheduleCleanup(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(60, function () {
            Output::info('Pruning Stale Connections');
            PruneStaleConnections::dispatch();

            Output::info('Pinging Inactive Connections');
            PingInactiveConnections::dispatch();
        });
    }
}
