<?php

namespace Laravel\Reverb\Managers;

use Illuminate\Support\Arr;
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
     *
     * @return array<string, \Laravel\Reverb\Channels\Channel>
     */
    public function all(): array
    {
        return $this->channels();
    }

    /**
     * Find the given channel
     */
    public function find(string $channel): ?Channel
    {
        return $this->channels($channel);
    }

    /**
     * Find the given channel or create it if it doesn't exist.
     */
    public function findOrCreate(string $channelName): Channel
    {
        if ($channel = $this->find($channelName)) {
            return $channel;
        }

        $channel = ChannelBroker::create($channelName);

        $this->applications[$this->application->id()][$channel->name()] = $channel;

        return $channel;
    }

    /**
     * Get all the connections for the given channels.
     *
     * @return array<string, \Laravel\Reverb\Servers\Reverb\ChannelConnection>
     */
    public function connections(?string $channel = null): array
    {
        $channels = Arr::wrap($this->channels($channel));

        return array_reduce($channels, function ($carry, $channel) {
            return $carry + $channel->connections();
        }, []);
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
     * Remove the given channel.
     */
    public function remove(Channel $channel): void
    {
        unset($this->applications[$this->application->id()][$channel->name()]);
    }

    /**
     * Get the given channel.
     */
    public function channel(string $channel): Channel
    {
        return $this->channels($channel);
    }

    /**
     * Get the channels.
     *
     * @return \Laravel\Reverb\Channels\Channel|array<string, \Laravel\Reverb\Channels\Channel>
     */
    public function channels(?string $channel = null): Channel|array|null
    {
        if (! isset($this->applications[$this->application->id()])) {
            $this->applications[$this->application->id()] = [];
        }

        if ($channel) {
            return $this->applications[$this->application->id()][$channel] ?? null;
        }

        return $this->applications[$this->application->id()];
    }

    /**
     * Flush the channel manager repository.
     */
    public function flush(): void
    {
        app(ApplicationProvider::class)
            ->all()
            ->each(function (Application $application) {
                $this->applications[$application->id()] = [];
            });
    }
}
