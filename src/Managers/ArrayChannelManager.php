<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Laravel\Reverb\Application;
use Laravel\Reverb\Channels\Channel;
use Laravel\Reverb\Channels\ChannelBroker;
use Laravel\Reverb\Concerns\InteractsWithApplications;
use Laravel\Reverb\Contracts\ApplicationProvider;
use Laravel\Reverb\Contracts\ChannelManager as ChannelManagerInterface;
use Laravel\Reverb\Contracts\Connection;

class ArrayChannelManager implements ChannelManagerInterface
{
    use InteractsWithApplications;

    /**
     * Application store.
     *
     * @var array<string, array<string, array<string, \Laravel\Reverb\Channels\Channel>>>
     */
    protected $applications = [];

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
     * Get all the channels.
     */
    public function all(): Collection
    {
        return collect($this->channels());
    }

    /**
     * Find the given channel
     */
    public function find(string $channel): Channel
    {
        return $this->channels($channel);
    }

    /**
     * Get all the connections for the given channels.
     */
    public function connections(string $channel = null): Collection
    {
        $channels = Collection::wrap($this->channels($channel));

        return $channels->reduce(function ($carry, $channel) {
            return $carry = $carry->merge($channel->connections());
        }, collect());
    }

    /**
     * Unsubscribe from all channels.
     */
    public function unsubscribeFromAll(Connection $connection): void
    {
        foreach ($this->channels() as $channel) {
            $channel->unsubscribe($connection);
        }
    }

    /**
     * Get the given channel.
     */
    public function channel(string $channel): array
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels.
     */
    public function channels(string $channel = null): array|Channel
    {
        if (! isset($this->applications[$this->application->id()])) {
            $this->applications[$this->application->id()] = [];
        }

        if ($channel) {
            if (! isset($this->applications[$this->application->id()][$channel])) {
                $this->applications[$this->application->id()][$channel] = ChannelBroker::create($channel);
            }

            return $this->applications[$this->application->id()][$channel];
        }

        return $this->applications[$this->application->id()] ?? [];
    }

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void
    {
        App::make(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                $this->applications[$application->id()] = [];
            });
    }
}
