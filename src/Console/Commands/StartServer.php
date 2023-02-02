<?php

namespace Laravel\Reverb\Console\Commands;

use Illuminate\Console\Command;

class StartServer extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:start
                {--server=ratchet}
                {--host=}
                {--port=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the Reverb server';

    /**
     * Execute the console command.
     */
    public function handle(): mixed
    {
        $server = $this->option('server') ?: config('reverb.default');

        return match ($server) {
            'ratchet' => $this->call('ratchet:start', [
                '--host' => $this->option('host'),
                '--port' => $this->option('port'),
            ]),
            default => $this->invalidServer($server),
        };
    }

    /**
     * Inform the user that the server type is invalid.
     */
    protected function invalidServer(string $server): int
    {
        $this->error("Invalid server: {$server}.");

        return 1;
    }
}
