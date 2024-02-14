<?php

namespace Laravel\Reverb\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;

class InstallCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reverb:install';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Install the Reverb dependencies';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->addEnviromentVariables();
        $this->publishConfiguration();
        $this->enableBroadcasting();
        $this->updateBroadcastingDriver();

        $this->components->info('Reverb installed successfully.');
    }

    /**
     * Add the Reverb variables to the environment file.
     */
    protected function addEnviromentVariables(): void
    {
        if (File::missing($env = app()->environmentFile())) {
            return;
        }

        $contents = File::get($env);
        $appId = random_int(100000, 999999);
        $appKey = Str::lower(Str::random(20));
        $appSecret = Str::lower(Str::random(20));

        $variables = Arr::where([
            'REVERB_APP_ID' => "REVERB_APP_ID={$appId}",
            'REVERB_APP_KEY' => "REVERB_APP_KEY={$appKey}",
            'REVERB_APP_SECRET' => "REVERB_APP_SECRET={$appSecret}",
            'REVERB_HOST' => 'REVERB_HOST="0.0.0.0"',
            'REVERB_PORT' => 'REVERB_PORT=8080',
            'REVERB_SCHEME' => 'REVERB_SCHEME=http',
            'REVERB_NEW_LINE' => null,
            'VITE_REVERB_APP_KEY' => 'VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"',
            'VITE_REVERB_HOST' => 'VITE_REVERB_HOST="${REVERB_HOST}"',
            'VITE_REVERB_PORT' => 'VITE_REVERB_PORT="${REVERB_PORT}"',
            'VITE_REVERB_SCHEME' => 'VITE_REVERB_SCHEME="${REVERB_SCHEME}"',
        ], function ($value, $key) use ($contents) {
            return ! Str::contains($contents, PHP_EOL.$key);
        });

        $variables = trim(implode(PHP_EOL, $variables));

        if ($variables === '') {
            return;
        }

        File::append(
            $env,
            Str::endsWith($contents, PHP_EOL) ? PHP_EOL.$variables.PHP_EOL : PHP_EOL.PHP_EOL.$variables.PHP_EOL,
        );
    }

    /**
     * Publish the Reverb configuration file.
     */
    protected function publishConfiguration(): void
    {
        $this->callSilently('vendor:publish', [
            '--provider' => 'Laravel\Reverb\ReverbServiceProvider',
            '--tag' => 'reverb-config',
        ]);
    }

    /**
     * Enable Laravel's broadcasting functionality.
     */
    protected function enableBroadcasting(): void
    {
        if (File::exists(base_path('routes/channels.php'))) {
            return;
        }

        $enable = confirm('Would you like to enable event broadcasting?', default: true);

        if (! $enable) {
            return;
        }

        if (version_compare($this->laravel->version(), '11.0', '<')) {
            $this->enableBroadcastServiceProvider();

            return;
        }

        $this->callSilently('install:broadcasting');
    }

    /**
     * Uncomment the "BroadcastServiceProvider" in the application configuration.
     */
    protected function enableBroadcastServiceProvider(): void
    {
        $config = File::get(app()->configPath('app.php'));

        if (Str::contains($config, '// App\Providers\BroadcastServiceProvider::class')) {
            File::replaceInFile(
                '// App\Providers\BroadcastServiceProvider::class',
                'App\Providers\BroadcastServiceProvider::class',
                app()->configPath('app.php'),
            );
        }
    }

    /**
     * Update the configured broadcasting driver.
     */
    protected function updateBroadcastingDriver(): void
    {
        $enable = confirm('Would you like to enable the Reverb broadcasting driver?', default: true);

        if (! $enable || File::missing($env = app()->environmentFile())) {
            return;
        }

        File::put(
            $env,
            Str::of(File::get($env))->replaceMatches('/(BROADCAST_(?:DRIVER|CONNECTION))=\w*/', function (array $matches) {
                return $matches[1].'=reverb';
            })
        );
    }
}
