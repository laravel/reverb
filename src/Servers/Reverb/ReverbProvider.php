<?php

namespace Laravel\Reverb\Servers\Reverb;

use Clue\React\Redis\Client;
use Clue\React\Redis\Factory;
use Illuminate\Console\Application as Artisan;
use Illuminate\Contracts\Foundation\Application;
use Laravel\Reverb\Concerns\InteractsWithAsyncRedis;
use Laravel\Reverb\Contracts\ServerProvider;
use Laravel\Reverb\Protocols\Pusher\EventDispatcher;
use Laravel\Reverb\Servers\Reverb\Console\Commands\StartServer;
use React\EventLoop\LoopInterface;

class ReverbProvider extends ServerProvider
{
    use InteractsWithAsyncRedis;

    /**
     * Indicates whether the server should publish events.
     *
     * @var bool
     */
    protected $publishesEvents;

    /**
     * Create a new Reverb server provider instance.
     */
    public function __construct(protected Application $app, protected array $config)
    {
        $this->publishesEvents = (bool) $this->config['scaling']['enabled'] ?? false;
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            Artisan::starting(function ($artisan) {
                $artisan->resolveCommands([
                    StartServer::class,
                ]);
            });
        }
    }

    /**
     * Determine whether the server should publish events.
     */
    public function shouldPublishEvents(): bool
    {
        return $this->publishesEvents;
    }

    /**
     * Publish the given payload on the configured channel.
     */
    public function publish(array $payload): void
    {
        $this->app->make(Client::class)
            ->publish(
                $this->config['scaling']['channel'] ?? 'reverb',
                json_encode($payload)
            );
    }

    /**
     * Subscribe to the configured channel.
     */
    public function subscribe(LoopInterface $loop): void
    {
        $redis = (new Factory($loop))->createLazyClient(
            $this->redisUrl()
        );

        $redis->subscribe($this->config['scaling']['channel'] ?? 'reverb');

        $redis->on('message', function (string $channel, string $payload) {
            $event = json_decode($payload, true);
            EventDispatcher::dispatchSynchronously(
                unserialize($event['application']),
                $event['payload']
            );
        });
    }

    /**
     * Enable publishing of events.
     */
    public function withPublishing(): void
    {
        $this->publishesEvents = true;
    }
}
