<?php

namespace Laravel\Reverb\Channels;

use Exception;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Contracts\Connection;

class Channel
{
    public function __construct(protected string $name)
    {
    }

    /**
     * Get the channel name.
     *
     * @return string
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Subscribe to the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @param  string|null  $auth
     * @param  string|null  $data
     * @return bool
     */
    public function subscribe(Connection $connection, ?string $auth = null, ?string $data = null): void
    {
        App::make(ChannelManager::class)
            ->for($connection->app())
            ->subscribe($this, $connection, $data ? json_decode($data, true) : []);
    }

    /**
     * Unsubscribe from the given channel.
     *
     * @param  \Laravel\Reverb\Contracts\Connection  $connection
     * @return bool
     */
    public function unsubscribe(Connection $connection): void
    {
        App::make(ChannelManager::class)
            ->for($connection->app())
            ->unsubscribe($this, $connection);
    }

    /**
     * Send a message to all connections subscribed to the channel.
     *
     * @param  array  $payload
     * @return void
     */
    public function broadcast(Application $app, array $payload, Connection $except = null)
    {
        App::make(ChannelManager::class)
            ->for($app)
            ->connections($this)->each(function ($data) use ($payload, $except) {
                $connection = is_object($data['connection']) ? $data['connection'] : unserialize($data['connection']);

                if ($except && $except->identifier() === $connection->identifier()) {
                    return;
                }

                if (isset($payload['except']) && $payload['except'] === $connection->identifier()) {
                    return;
                }

                try {
                    $connection->send(json_encode($payload));
                } catch (Exception $e) {
                    echo $e->getMessage().PHP_EOL;
                }
            });
    }

    /**
     * Get the data associated with the channel.
     *
     * @return array
     */
    public function data(Application $app)
    {
        return [];
    }
}
