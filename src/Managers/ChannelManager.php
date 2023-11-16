<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\EnsuresIntegrity;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\Connection;

class ChannelManager implements ChannelManagerInterface
{
    use EnsuresIntegrity, InteractsWithApplications;

    /**
     * Connection store.
     *
     * @var array<string, array<string, array<string, \Laravel\Reverb\Connection>>>
     */
    protected $connections = [];

    /**
     * The appliation instance.
     *
     * @var \Laravel\Reverb\Application
     */
    protected $application;

    /**
     * Get the application instance.
     */
    public function app(): ?Application
    {
        return $this->application;
    }

    /**
     * Subscribe to a channel.
     */
    public function subscribe(Channel $channel, Connection $connection, $data = []): void
    {
        $this->connections[$this->application->id()][$channel->name()][$connection->identifier()] = $connection;
    }

    /**
     * Unsubscribe from a channel.
     */
    public function unsubscribe(Channel $channel, Connection $connection): void
    {
        unset($this->connections[$this->application->id()][$channel->name()][$connection->identifier()]);
    }

    /**
     * Get all the channels.
     */
    public function all(): Collection
    {
        return $this->channels()->map(function ($connections, $name) {
            return ChannelBroker::create($name);
        });
    }

    /**
     * Unsubscribe from all channels.
     */
    public function unsubscribeFromAll(Connection $connection): void
    {
        $this->channels()->each(function ($connections, $name) use ($connection) {
            ChannelBroker::create($name)->unsubscribe($connection);
        });
    }

    /**
     * Get all connections for the given channel.
     *
     * @return <array string, \Laravel\Reverb\Connection>
     */
    public function connections(Channel $channel): array
    {
        return $this->connections[$this->application->id()][$channel->name()] ?? [];
    }

    /**
     * Get the given channel from the cache.
     */
    protected function channel(Channel $channel): Collection
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels from the cache.
     */
    protected function channels(Channel $channel = null): Collection
    {
        $channels = $this->connections[$this->application->id()];

        if ($channel) {
            return collect($channels[$channel->name()] ?? []);
        }

        return collect($channels ?: []);
    }

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void
    {
        App::make(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                $this->connections[$application->id()] = [];
            });
    }
}
