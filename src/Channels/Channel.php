<?php

namespace Laravel\Reverb\Channels;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;
use Laravel\Reverb\Output;

class Channel
{
    public function __construct(protected string $name)
    {
    }

    /**
     * Get the channel name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Subscribe to the given channel.
     */
    public function subscribe(Connection $connection, string $auth = null, string $data = null): void
    {
        App::make(ChannelManager::class)
            ->for($connection->app())
            ->subscribe($this, $connection, $data ? json_decode($data, true) : []);
    }

    /**
     * Unsubscribe from the given channel.
     */
    public function unsubscribe(Connection $connection): void
    {
        App::make(ChannelManager::class)
            ->for($connection->app())
            ->unsubscribe($this, $connection);
    }

    /**
     * Send a message to all connections subscribed to the channel.
     */
    public function broadcast(Application $app, array $payload, Connection $except = null): void
    {
        collect(App::make(ChannelManager::class)->for($app)->connections($this))
            ->each(function ($connection) use ($payload, $except) {
                if ($except && $except->id() === $connection->id()) {
                    return;
                }

                if (isset($payload['except']) && $payload['except'] === $connection->id()) {
                    return;
                }

                try {
                    $connection->send(
                        json_encode(
                            Arr::except($payload, 'except')
                        )
                    );
                } catch (Exception $e) {
                    Output::error('Broadcasting to '.$connection->id().' resulted in an error');
                    Output::info($e->getMessage());
                }
            });
    }

    /**
     * Get the data associated with the channel.
     */
    public function data(Application $app): array
    {
        return [];
    }
}
