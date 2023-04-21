<?php

namespace Laravel\Reverb\Servers\Swoole\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Servers\Swoole\Factory;

class StartServer extends Command
{
    use InteractsWithAsyncRedis;

    protected $server;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'swoole:start
                {--host=}
                {--port=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Swoole Reverb server';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->laravel->instance(Logger::class, new CliLogger($this->output));

        $config = $this->laravel['config']['reverb.servers.swoole'];
        $host = $this->option('host') ?: $config['host'];
        $port = $this->option('port') ?: $config['port'];

        $server = Factory::make($host, $port);
        $server->on('Start', fn () => $this->components->info("Starting server on {$host}:{$port}"));

        $server->start();
    }
}
