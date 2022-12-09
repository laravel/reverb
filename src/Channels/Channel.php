<?php

namespace Laravel\Reverb\Channels;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Connection;
use Laravel\Reverb\Contracts\ChannelManager;
use Laravel\Reverb\Output;

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
     * @param  \Laravel\Reverb\Connection  $connection
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
     * @param  \Laravel\Reverb\Connection  $connection
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
     * @param  \Laravel\Reverb\Application  $app
     * @param  array  $payload
     * @param  \Laravel\Reverb\Connection|null  $except
     * @return void
     */
    public function broadcast(Application $app, array $payload, Connection $except = null)
    {
        App::make(ChannelManager::class)
            ->for($app)
            ->hydratedConnections($this)->each(function ($connection) use ($payload, $except) {
                $connection = Connection::hydrate($connection);
                if ($except && $except->identifier() === $connection->identifier()) {
                    return;
                }

                if (isset($payload['except']) && $payload['except'] === $connection->identifier()) {
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
     *
     * @param  \Laravel\Reverb\Application  $app
     * @return array
     */
    public function data(Application $app)
    {
        return [];
    }
}
