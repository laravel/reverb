<?php

namespace Laravel\Reverb\Servers\Reverb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Laravel\Reverb\Application;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\Logger;
use Laravel\Reverb\Jobs\PingInactiveConnections;
use Laravel\Reverb\Jobs\PruneStaleConnections;
use Laravel\Reverb\Loggers\CliLogger;
use Laravel\Reverb\Protocols\Pusher\Channels\ChannelConnection;
use Laravel\Reverb\Protocols\Pusher\Contracts\ChannelManager;
use Laravel\Reverb\Servers\Reverb\Factory as ServerFactory;
use Laravel\Reverb\Servers\Reverb\Http\Server;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use Symfony\Component\Console\Command\SignalableCommandInterface;

class StartServer extends Command implements SignalableCommandInterface
{
    use InteractsWithAsyncRedis;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:start
                {--host=}
                {--port=}
                {--debug}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the Reverb server';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('debug')) {
            $this->laravel->instance(Logger::class, new CliLogger($this->output));
        }

        $config = $this->laravel['config']['reverb.servers.reverb'];
        $host = $this->option('host') ?: $config['host'];
        $port = $this->option('port') ?: $config['port'];

        $loop = Loop::get();

        $server = ServerFactory::make($host, $port, loop: $loop);

        $this->bindRedis($loop);
        $this->subscribeToRedis($loop);
        $this->scheduleCleanup($loop);
        $this->checkForRestartSignal($server, $loop, $host, $port);

        $this->components->info("Starting server on {$host}:{$port}");

        $server->start();
    }

    /**
     * Get the list of signals handled by the command.
     */
    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    /**
     * Handle the signals sent to the server.
     */
    public function handleSignal(int $signal): void
    {
        $this->gracefullyDisconnect();
    }

    /**
     * Use the event loop to schedule periodic cleanup of connections.
     */
    protected function scheduleCleanup(LoopInterface $loop): void
    {
        $loop->addPeriodicTimer(60, function () {
            PruneStaleConnections::dispatch();

            PingInactiveConnections::dispatch();
        });
    }

    /**
     * Check to see whether the restart signal has been sent.
     */
    protected function checkForRestartSignal(Server $server, LoopInterface $loop, string $host, string $port): void
    {
        $lastRestart = Cache::get('laravel:reverb:restart');

        $loop->addPeriodicTimer(5, function () use ($server, $host, $port, $lastRestart) {
            if ($lastRestart === Cache::get('laravel:pulse:restart')) {
                return;
            }

            $this->gracefullyDisconnect();

            $server->stop();

            $this->components->info("Stopping server on {$host}:{$port}");
        });
    }

    /**
     * Gracefully disconnect all connections.
     */
    protected function gracefullyDisconnect(): void
    {
        $this->laravel->make(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                collect(
                    $this->laravel->make(ChannelManager::class)
                        ->for($application)
                        ->connections()
                )->each(fn (ChannelConnection $connection) => $connection->disconnect());
            });
    }
}
