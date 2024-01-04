<?php

namespace Laravel\Reverb\Servers\Reverb\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Reverb\Concerns\InteractsWithServerState;

class RestartServer extends Command
{
    use InteractsWithServerState;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:restart';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Signal Reverb to restart the server';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if (! $state = $this->getState()) {
            $this->error('No Reverb server running.');

            return;
        }

        $this->sendStopSignal($state);

        $this->waitForServerToStop(fn () => $this->call('reverb:start', [
            '--host' => $state['HOST'],
            '--port' => $state['PORT'],
            '--debug' => $state['DEBUG'],
        ]));
    }

    /**
     * Send the stop signal to the running server.
     *
     * @param  array{HOST: string, PORT: int, DEBUG: bool, RESTART: bool}  $state
     */
    protected function sendStopSignal(array $state): void
    {
        $this->components->info('Sending stop signal to Reverb server.');

        $this->setState($state['HOST'], $state['PORT'], $state['DEBUG'], true);
    }

    /**
     * Run the callback when the server has stopped.
     */
    protected function waitForServerToStop(callable $callback): void
    {
        while ($this->serverIsRunning()) {
            usleep(1000);
        }

        $callback();
    }
}
